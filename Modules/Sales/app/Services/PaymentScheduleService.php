<?php

namespace Modules\Sales\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Models\Contract;
use Modules\Collections\Models\PaymentSchedule;
use Modules\Inventory\Models\LotFinancialTemplate;
use Modules\Inventory\Models\ManzanaFinancingRule;

class PaymentScheduleService
{
    /**
     * Genera un cronograma de pagos inteligente basado en LotFinancialTemplate
     */
    public function generateIntelligentSchedule(Contract $contract, array $options = []): array
    {
        try {
            // Obtener lote: desde reserva o directamente del contrato
            $lot = null;
            if ($contract->reservation && $contract->reservation->lot) {
                // Contrato con reserva
                $lot = $contract->reservation->lot;
            } elseif ($contract->lot_id) {
                // Contrato directo
                $lot = $contract->lot;
            }
            
            if (!$lot) {
                throw new Exception('El contrato debe tener un lote asociado (directamente o a través de reserva)');
            }

            $financialTemplate = $lot->financialTemplate;
            $manzanaRule = $lot->manzana->financingRule ?? null;

            if (!$financialTemplate) {
                throw new Exception("El lote {$lot->num_lot} no tiene plantilla financiera configurada");
            }

            // Determinar tipo de pago y validaciones
            $paymentType = $options['payment_type'] ?? 'installments';
            $installments = $options['installments'] ?? 24;

            // Validar reglas de la manzana si existen
            if ($manzanaRule) {
                $this->validateManzanaRules($manzanaRule, $paymentType, $installments);
            }

            // Generar cronograma según el tipo de pago
            if ($paymentType === 'cash') {
                return $this->generateCashSchedule($contract, $financialTemplate);
            } else {
                return $this->generateInstallmentSchedule($contract, $financialTemplate, $installments, $options);
            }

        } catch (Exception $e) {
            Log::error('Error generando cronograma inteligente', [
                'contract_id' => $contract->contract_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Valida las reglas de financiamiento de la manzana
     */
    private function validateManzanaRules(ManzanaFinancingRule $rule, string $paymentType, int $installments): void
    {
        if ($paymentType === 'cash' && $rule->isCashOnly()) {
            return; // Válido
        }

        if ($paymentType === 'installments') {
            if ($rule->isCashOnly()) {
                throw new Exception('Esta manzana solo acepta pagos al contado');
            }

            if (!$rule->isValidInstallmentOption($installments)) {
                $validOptions = implode(', ', $rule->getAvailableInstallmentOptions());
                throw new Exception("Número de cuotas inválido. Opciones disponibles: {$validOptions}");
            }
        }
    }

    /**
     * Genera cronograma para pago al contado
     */
    private function generateCashSchedule(Contract $contract, LotFinancialTemplate $template): array
    {
        if (!$template->hasCashPrice()) {
            throw new Exception('Este lote no tiene precio de contado disponible');
        }

        $schedules = [];
        $dueDate = Carbon::parse($contract->sign_date ?? now())->addDays(30);

        $schedules[] = [
            'contract_id' => $contract->contract_id,
            'installment_number' => 1,
            'due_date' => $dueDate->format('Y-m-d'),
            'amount' => $template->precio_contado,
            'status' => 'pendiente'
        ];

        return $schedules;
    }

    /**
     * Genera cronograma para pago a plazos
     */
    private function generateInstallmentSchedule(
        Contract $contract, 
        LotFinancialTemplate $template, 
        int $installments, 
        array $options = []
    ): array {
        if (!$template->hasInstallmentOptions()) {
            throw new Exception('Este lote no tiene opciones de financiamiento disponibles');
        }

        $monthlyAmount = $template->getInstallmentAmount($installments);
        if ($monthlyAmount <= 0) {
            throw new Exception("No hay monto configurado para {$installments} cuotas");
        }

        $schedules = [];
        
        // USAR start_date de options si está disponible (para imports desde Logicware)
        // De lo contrario, usar sign_date del contrato o fecha actual
        if (isset($options['start_date'])) {
            $startDate = Carbon::parse($options['start_date']);
            Log::info('[PaymentScheduleService] 📅 Usando start_date desde options', [
                'start_date' => $options['start_date'],
                'parsed' => $startDate->format('Y-m-d')
            ]);
        } else {
            $startDate = Carbon::parse($contract->sign_date ?? now());
            Log::info('[PaymentScheduleService] 📅 Usando fecha desde contrato/actual', [
                'sign_date' => $contract->sign_date,
                'parsed' => $startDate->format('Y-m-d')
            ]);
        }
        
        $installmentNumber = 1;

        // Cuota inicial si existe
        if ($template->cuota_inicial > 0) {
            $schedules[] = [
                'contract_id' => $contract->contract_id,
                'installment_number' => $installmentNumber++,
                'due_date' => $startDate->copy()->addDays(15)->format('Y-m-d'),
                'amount' => $template->cuota_inicial,
                'status' => 'pendiente'
            ];
        }

        // Cuotas mensuales regulares
        for ($i = 1; $i <= $installments; $i++) {
            $dueDate = $startDate->copy()->addMonths($i);
            
            $schedules[] = [
                'contract_id' => $contract->contract_id,
                'installment_number' => $installmentNumber++,
                'due_date' => $dueDate->format('Y-m-d'),
                'amount' => $monthlyAmount,
                'status' => 'pendiente'
            ];
        }

        // Cuota balón si existe
        if ($template->cuota_balon > 0) {
            $balloonDate = $startDate->copy()->addMonths($installments + 1);
            
            $schedules[] = [
                'contract_id' => $contract->contract_id,
                'installment_number' => $installmentNumber++,
                'due_date' => $balloonDate->format('Y-m-d'),
                'amount' => $template->cuota_balon,
                'status' => 'pendiente'
            ];
        }

        return $schedules;
    }

    /**
     * Guarda el cronograma en la base de datos
     */
    public function saveSchedule(array $schedules): array
    {
        $savedSchedules = [];

        DB::transaction(function () use ($schedules, &$savedSchedules) {
            foreach ($schedules as $scheduleData) {
                // Insertar directamente sin timestamps (tabla no tiene updated_at/created_at)
                $scheduleId = DB::table('payment_schedules')->insertGetId($scheduleData);
                
                // Cargar el modelo creado
                $schedule = PaymentSchedule::find($scheduleId);
                $savedSchedules[] = $schedule;
            }
        });

        return $savedSchedules;
    }

    /**
     * Obtiene las opciones de financiamiento disponibles para un contrato
     */
    public function getFinancingOptions(Contract $contract): array
    {
        if (!$contract->reservation || !$contract->reservation->lot) {
            return [];
        }

        $lot = $contract->reservation->lot;
        $financialTemplate = $lot->financialTemplate;
        $manzanaRule = $lot->manzana->financingRule ?? null;

        if (!$financialTemplate) {
            return [];
        }

        $options = [
            'cash_available' => $financialTemplate->hasCashPrice(),
            'installments_available' => $financialTemplate->hasInstallmentOptions(),
            'cash_price' => $financialTemplate->precio_contado,
            'sale_price' => $financialTemplate->precio_venta,
            'down_payment' => $financialTemplate->cuota_inicial,
            'balloon_payment' => $financialTemplate->cuota_balon,
            'installment_options' => []
        ];

        // Agregar opciones de cuotas disponibles
        $availableInstallments = $financialTemplate->getAvailableInstallmentOptions();
        
        foreach ($availableInstallments as $months => $amount) {
            // Validar con reglas de manzana si existen
            if ($manzanaRule && !$manzanaRule->isValidInstallmentOption($months)) {
                continue;
            }

            $options['installment_options'][$months] = [
                'months' => $months,
                'monthly_amount' => $amount,
                'total_amount' => $financialTemplate->getTotalPaymentForInstallments($months)
            ];
        }

        return $options;
    }

    /**
     * Valida si un contrato puede generar cronograma
     */
    public function canGenerateSchedule(Contract $contract): array
    {
        $result = [
            'can_generate' => false,
            'reasons' => []
        ];

        // Obtener lote: desde reserva o directamente del contrato
        $lot = null;
        if ($contract->reservation && $contract->reservation->lot) {
            // Contrato con reserva
            $lot = $contract->reservation->lot;
        } elseif ($contract->lot_id) {
            // Contrato directo - cargar relación si no está cargada
            $lot = $contract->lot ?? $contract->load('lot')->lot;
        }
        
        if (!$lot) {
            $result['reasons'][] = 'El contrato no tiene lote asociado (ni directamente ni a través de reserva)';
            return $result;
        }

        // Verificar plantilla financiera
        $financialTemplate = $lot->financialTemplate;
        if (!$financialTemplate) {
            $result['reasons'][] = 'El lote no tiene plantilla financiera configurada';
            return $result;
        }

        // Verificar que tenga al menos una opción de pago
        if (!$financialTemplate->hasCashPrice() && !$financialTemplate->hasInstallmentOptions()) {
            $result['reasons'][] = 'El lote no tiene opciones de pago configuradas';
            return $result;
        }

        // Verificar que no tenga cronograma existente
        if ($contract->paymentSchedules()->count() > 0) {
            $result['reasons'][] = 'El contrato ya tiene cronograma de pagos generado';
            return $result;
        }

        $result['can_generate'] = true;
        return $result;
    }
}