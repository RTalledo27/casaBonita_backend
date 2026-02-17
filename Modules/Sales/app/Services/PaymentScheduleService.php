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

            // Calcular factor de descuento si el contrato tiene precio menor al template
            $discountFactor = $this->calculateDiscountFactor($contract, $financialTemplate);

            // Validar reglas de la manzana si existen
            if ($manzanaRule) {
                $this->validateManzanaRules($manzanaRule, $paymentType, $installments);
            }

            // Generar cronograma según el tipo de pago
            if ($paymentType === 'cash') {
                return $this->generateCashSchedule($contract, $financialTemplate, $discountFactor);
            } else {
                return $this->generateInstallmentSchedule($contract, $financialTemplate, $installments, $options, $discountFactor);
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
     * Calcula el factor de descuento comparando precio del contrato vs template
     * Si el contrato tiene un precio menor al template, retorna un factor < 1.0
     */
    private function calculateDiscountFactor(Contract $contract, LotFinancialTemplate $template): float
    {
        $templatePrecioVenta = (float) ($template->precio_venta ?? 0);
        $contractDiscount = (float) ($contract->discount ?? 0);

        // Solo aplicar descuento si está explícitamente definido en el contrato
        if ($contractDiscount > 0 && $templatePrecioVenta > 0) {
            $factor = 1 - ($contractDiscount / $templatePrecioVenta);
            Log::info('[PaymentScheduleService] 🏷️ Descuento del contrato', [
                'contract_id' => $contract->contract_id,
                'template_precio_venta' => $templatePrecioVenta,
                'contract_discount' => $contractDiscount,
                'discount_factor' => round($factor, 4),
            ]);
            return max($factor, 0.01);
        }

        return 1.0; // Sin descuento
    }

    /**
     * Genera cronograma para pago al contado
     */
    private function generateCashSchedule(Contract $contract, LotFinancialTemplate $template, float $discountFactor = 1.0): array
    {
        if (!$template->hasCashPrice()) {
            throw new Exception('Este lote no tiene precio de contado disponible');
        }

        $schedules = [];
        $dueDate = Carbon::parse($contract->sign_date ?? now())->addDays(30);

        $cashAmount = round($template->precio_contado * $discountFactor, 2);

        $schedules[] = [
            'contract_id' => $contract->contract_id,
            'installment_number' => 1,
            'due_date' => $dueDate->format('Y-m-d'),
            'amount' => $cashAmount,
            'status' => 'pendiente'
        ];

        if ($discountFactor < 1.0) {
            Log::info('[PaymentScheduleService] 💰 Cronograma contado con descuento', [
                'contract_id' => $contract->contract_id,
                'original' => $template->precio_contado,
                'con_descuento' => $cashAmount,
            ]);
        }

        return $schedules;
    }

    /**
     * Genera cronograma para pago a plazos
     */
    private function generateInstallmentSchedule(
        Contract $contract, 
        LotFinancialTemplate $template, 
        int $installments, 
        array $options = [],
        float $discountFactor = 1.0
    ): array {
        if (!$template->hasInstallmentOptions()) {
            throw new Exception('Este lote no tiene opciones de financiamiento disponibles');
        }

        $monthlyAmount = $template->getInstallmentAmount($installments);
        if ($monthlyAmount <= 0) {
            throw new Exception("No hay monto configurado para {$installments} cuotas");
        }

        // Aplicar factor de descuento a todos los montos
        $monthlyAmount = round($monthlyAmount * $discountFactor, 2);
        $cuotaInicial = round((float) $template->cuota_inicial * $discountFactor, 2);
        $cuotaBalon = round((float) $template->cuota_balon * $discountFactor, 2);

        if ($discountFactor < 1.0) {
            Log::info('[PaymentScheduleService] 🏷️ Montos con descuento aplicado', [
                'contract_id' => $contract->contract_id,
                'discount_factor' => round($discountFactor, 4),
                'cuota_mensual_original' => $template->getInstallmentAmount($installments),
                'cuota_mensual_descuento' => $monthlyAmount,
                'cuota_inicial_original' => $template->cuota_inicial,
                'cuota_inicial_descuento' => $cuotaInicial,
                'cuota_balon_original' => $template->cuota_balon,
                'cuota_balon_descuento' => $cuotaBalon,
            ]);
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
        if ($cuotaInicial > 0) {
            $schedules[] = [
                'contract_id' => $contract->contract_id,
                'installment_number' => $installmentNumber++,
                'due_date' => $startDate->copy()->addDays(15)->format('Y-m-d'),
                'amount' => $cuotaInicial,
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
        if ($cuotaBalon > 0) {
            $balloonDate = $startDate->copy()->addMonths($installments + 1);
            
            $schedules[] = [
                'contract_id' => $contract->contract_id,
                'installment_number' => $installmentNumber++,
                'due_date' => $balloonDate->format('Y-m-d'),
                'amount' => $cuotaBalon,
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
        $lot = null;
        if ($contract->reservation && $contract->reservation->lot) {
            $lot = $contract->reservation->lot;
        } elseif ($contract->lot_id) {
            $lot = $contract->lot;
        }

        if (!$lot) {
            return [];
        }

        $financialTemplate = $lot->financialTemplate;
        $manzanaRule = $lot->manzana->financingRule ?? null;

        if (!$financialTemplate) {
            return [];
        }

        // Calcular factor de descuento
        $discountFactor = $this->calculateDiscountFactor($contract, $financialTemplate);
        $contractDiscount = (float) ($contract->discount ?? 0);

        $options = [
            'cash_available' => $financialTemplate->hasCashPrice(),
            'installments_available' => $financialTemplate->hasInstallmentOptions(),
            'cash_price' => round((float) $financialTemplate->precio_contado * $discountFactor, 2),
            'sale_price' => (float) $financialTemplate->precio_venta,
            'sale_price_with_discount' => round((float) $financialTemplate->precio_venta * $discountFactor, 2),
            'discount' => $contractDiscount,
            'discount_factor' => round($discountFactor, 4),
            'down_payment' => round((float) $financialTemplate->cuota_inicial * $discountFactor, 2),
            'balloon_payment' => round((float) $financialTemplate->cuota_balon * $discountFactor, 2),
            'installment_options' => []
        ];

        // Agregar opciones de cuotas disponibles
        $availableInstallments = $financialTemplate->getAvailableInstallmentOptions();
        
        foreach ($availableInstallments as $months => $amount) {
            // Validar con reglas de manzana si existen
            if ($manzanaRule && !$manzanaRule->isValidInstallmentOption($months)) {
                continue;
            }

            $discountedAmount = round($amount * $discountFactor, 2);

            $options['installment_options'][$months] = [
                'months' => $months,
                'monthly_amount' => $discountedAmount,
                'monthly_amount_original' => (float) $amount,
                'total_amount' => round($financialTemplate->getTotalPaymentForInstallments($months) * $discountFactor, 2)
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