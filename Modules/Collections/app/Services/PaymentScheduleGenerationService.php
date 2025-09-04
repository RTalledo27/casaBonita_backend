<?php

namespace Modules\Collections\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\PaymentSchedule;
use Modules\Sales\Services\PaymentScheduleService;
use Modules\Collections\Models\AccountReceivable;
use Modules\Inventory\Models\LotFinancialTemplate;
use Modules\Inventory\Models\ManzanaFinancingRule;

class PaymentScheduleGenerationService
{
    protected $paymentScheduleService;
    protected $collectionService;

    public function __construct(
        PaymentScheduleService $paymentScheduleService,
        CollectionService $collectionService
    ) {
        $this->paymentScheduleService = $paymentScheduleService;
        $this->collectionService = $collectionService;
    }

    /**
     * Genera cronogramas de pagos para todos los contratos activos sin cronograma
     */
    public function generateBulkPaymentSchedules(array $options = []): array
    {
        try {
            Log::info('🚀 Iniciando generación masiva de cronogramas de pagos');

            // Obtener contratos activos sin cronograma
            $contracts = $this->getContractsWithoutSchedule();
            
            if ($contracts->isEmpty()) {
                return [
                    'success' => true,
                    'message' => 'No hay contratos que requieran generación de cronogramas',
                    'processed' => 0,
                    'errors' => []
                ];
            }

            $processed = 0;
            $errors = [];
            $results = [];

            DB::beginTransaction();

            foreach ($contracts as $contract) {
                try {
                    Log::info("📋 Procesando contrato {$contract->contract_number}", [
                        'contract_id' => $contract->contract_id
                    ]);

                    // Generar cronograma inteligente basado en lot financial template
            $scheduleResult = $this->generateScheduleForContract($contract, $options);
            
            if ($scheduleResult['success']) {
                $processed++;
                $results[] = [
                    'contract_id' => $contract->contract_id,
                    'contract_number' => $contract->contract_number,
                    'schedules_created' => $scheduleResult['schedules_count'],
                    'total_amount' => $scheduleResult['total_amount'],
                    'payment_type' => $scheduleResult['payment_type'],
                    'installments' => $scheduleResult['installments']
                ];
            } else {
                $errors[] = [
                    'contract_id' => $contract->contract_id,
                    'contract_number' => $contract->contract_number,
                    'error' => $scheduleResult['error']
                ];
            }

                } catch (Exception $e) {
                    Log::error("❌ Error procesando contrato {$contract->contract_number}", [
                        'contract_id' => $contract->contract_id,
                        'error' => $e->getMessage()
                    ]);
                    
                    $errors[] = [
                        'contract_id' => $contract->contract_id,
                        'contract_number' => $contract->contract_number,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            Log::info('✅ Generación masiva completada', [
                'processed' => $processed,
                'errors' => count($errors)
            ]);

            return [
                'success' => true,
                'message' => "Procesados {$processed} contratos exitosamente",
                'processed' => $processed,
                'errors' => $errors,
                'results' => $results
            ];

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('❌ Error en generación masiva de cronogramas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Genera cronograma para un contrato específico
     */
    public function generateScheduleForContract(Contract $contract, array $options = []): array
    {
        try {
            // Validar que el contrato puede generar cronograma
            $validation = $this->paymentScheduleService->canGenerateSchedule($contract);
            
            if (!$validation['can_generate']) {
                return [
                    'success' => false,
                    'error' => 'No se puede generar cronograma: ' . implode(', ', $validation['reasons'])
                ];
            }

            // Obtener lote: desde reserva o directamente del contrato
            $lot = null;
            if ($contract->reservation && $contract->reservation->lot) {
                // Contrato con reserva
                $lot = $contract->reservation->lot;
            } elseif ($contract->lot_id) {
                // Contrato directo
                $lot = $contract->lot ?? $contract->load('lot')->lot;
            }
            
            if (!$lot) {
                throw new Exception("Contrato {$contract->contract_id} no tiene lote asociado");
            }
            
            // Obtener template financiero y reglas de manzana
            $template = $lot->financialTemplate;
            $manzanaRule = $lot->manzana->financingRule;

            if (!$template) {
                throw new Exception("Contrato {$contract->contract_id} no tiene template financiero configurado para el lote {$lot->num_lot} de la manzana {$lot->manzana->name}");
            }

            // Determinar opciones de pago basadas en template y reglas
            $paymentOptions = $this->determinePaymentOptions($template, $manzanaRule, $options, $contract);

            // Validar que las opciones sean válidas
            if ($paymentOptions['total_amount'] <= 0) {
                throw new Exception("No se pudo determinar un monto válido para el contrato {$contract->contract_id}");
            }

            // Preparar opciones para PaymentScheduleService
            $serviceOptions = [
                'payment_type' => $paymentOptions['payment_type'],
                'installments' => $paymentOptions['installments'],
                'start_date' => $paymentOptions['start_date']
            ];

            // Generar cronograma usando PaymentScheduleService
            $schedules = $this->paymentScheduleService->generateIntelligentSchedule($contract, $serviceOptions);
            
            // Guardar cronograma en la base de datos
            $savedSchedules = [];
            foreach ($schedules as $scheduleData) {
                $savedSchedules[] = PaymentSchedule::create($scheduleData);
            }
            
            $result = [
                 'success' => true,
                 'schedules' => $savedSchedules,
                 'schedules_count' => count($savedSchedules),
                 'total_amount' => array_sum(array_column($schedules, 'amount')),
                 'payment_type' => $paymentOptions['payment_type'],
                 'installments' => $paymentOptions['installments']
             ];

            // Crear cuentas por cobrar automáticamente
            $accountsReceivable = $this->createAccountsReceivableFromGeneratedSchedules($contract, $result['schedules']);

            return [
                'success' => true,
                'contract_id' => $contract->contract_id,
                'schedules_count' => count($result['schedules']),
                'accounts_receivable_created' => count($accountsReceivable),
                'total_amount' => $paymentOptions['total_amount'],
                'financing_amount' => $paymentOptions['financing_amount'],
                'payment_type' => $paymentOptions['payment_type'],
                'installments' => $paymentOptions['installments'],
                'monthly_payment' => $paymentOptions['monthly_payment'],
                'down_payment' => $paymentOptions['down_payment'],
                'balloon_payment' => $paymentOptions['balloon_payment'],
                'template_used' => [
                    'precio_lista' => $template->precio_lista,
                    'precio_venta' => $template->precio_venta,
                    'precio_contado' => $template->precio_contado,
                    'cuota_inicial' => $template->cuota_inicial
                ],
                'manzana_rule_applied' => $manzanaRule ? [
                    'financing_type' => $manzanaRule->financing_type,
                    'max_installments' => $manzanaRule->max_installments,
                    'allows_balloon_payment' => $manzanaRule->allows_balloon_payment
                ] : null,
                'accounts_receivable' => array_map(function($ar) {
                    return [
                        'ar_id' => $ar->ar_id,
                        'ar_number' => $ar->ar_number,
                        'due_date' => $ar->due_date,
                        'amount' => $ar->original_amount
                    ];
                }, $accountsReceivable)
            ];

        } catch (Exception $e) {
            Log::error('Error generando cronograma para contrato', [
                'contract_id' => $contract->contract_id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }



    /**
     * Obtiene contratos activos sin cronograma de pagos
     */
    protected function getContractsWithoutSchedule()
    {
        return Contract::with(['reservation.client', 'reservation.lot.financialTemplate', 'reservation.lot.manzana.financingRule'])
            ->where('status', 'active')
            ->whereDoesntHave('paymentSchedules')
            ->whereHas('reservation.lot.financialTemplate')
            ->get();
    }

    /**
     * Determina las opciones de pago basadas en template y reglas
     */
    protected function determinePaymentOptions(LotFinancialTemplate $template, ?ManzanaFinancingRule $manzanaRule, array $userOptions = [], ?Contract $contract = null): array
    {
        // Determinar fecha de inicio basada en la fecha de venta del contrato
        $defaultStartDate = Carbon::now()->addDays(15)->format('Y-m-d');
        if ($contract) {
            // Usar la fecha de venta del contrato (sign_date o contract_date) como base
            $contractDate = $contract->sign_date ?? $contract->contract_date ?? $contract->created_at;
            if ($contractDate) {
                // Iniciar cronograma el mes siguiente a la fecha de venta
                $defaultStartDate = Carbon::parse($contractDate)->addMonth()->startOfMonth()->format('Y-m-d');
            }
        }
        
        $options = [
            'payment_type' => $userOptions['payment_type'] ?? 'installments',
            'installments' => $userOptions['installments'] ?? 24,
            'start_date' => $userOptions['start_date'] ?? $defaultStartDate,
            'financing_amount' => 0,
            'monthly_payment' => 0,
            'down_payment' => 0,
            'balloon_payment' => 0,
            'total_amount' => 0
        ];

        // Validar con reglas de manzana si existen
        if ($manzanaRule) {
            // Forzar pago al contado si la manzana solo acepta contado
            if ($manzanaRule->isCashOnly()) {
                $options['payment_type'] = 'cash';
            }
            
            // Validar número de cuotas disponibles para la manzana
            if ($options['payment_type'] === 'installments' && $manzanaRule->allowsInstallments()) {
                $availableOptions = $manzanaRule->getAvailableInstallmentOptions();
                
                // Si las cuotas solicitadas no están disponibles, usar la más cercana
                if (!in_array($options['installments'], $availableOptions) && !empty($availableOptions)) {
                    // Buscar la opción más cercana
                    $closest = $availableOptions[0];
                    foreach ($availableOptions as $option) {
                        if (abs($option - $options['installments']) < abs($closest - $options['installments'])) {
                            $closest = $option;
                        }
                    }
                    $options['installments'] = $closest;
                }
            }
        }

        // Validar disponibilidad en template y calcular montos
        if ($options['payment_type'] === 'cash') {
            if ($template->hasCashPrice()) {
                $options['total_amount'] = $template->precio_contado;
                $options['financing_amount'] = $template->precio_contado;
            } else {
                // Si no hay precio de contado, cambiar a cuotas
                $options['payment_type'] = 'installments';
            }
        }

        if ($options['payment_type'] === 'installments') {
            if ($template->hasInstallmentOptions()) {
                // Verificar si el template tiene la opción de cuotas solicitada
                $monthlyPayment = $template->getInstallmentAmount($options['installments']);
                
                if ($monthlyPayment > 0) {
                    $options['monthly_payment'] = $monthlyPayment;
                    $options['down_payment'] = $template->cuota_inicial;
                    $options['balloon_payment'] = $template->cuota_balon;
                    $options['financing_amount'] = $template->getFinancingAmount();
                    $options['total_amount'] = $template->getTotalPaymentForInstallments($options['installments']);
                } else {
                    // Si no hay cuotas disponibles para el número solicitado, buscar alternativa
                    $availableOptions = $template->getAvailableInstallmentOptions();
                    if (!empty($availableOptions)) {
                        $alternativeInstallments = array_keys($availableOptions)[0]; // Tomar la primera disponible
                        $options['installments'] = $alternativeInstallments;
                        $options['monthly_payment'] = $availableOptions[$alternativeInstallments];
                        $options['down_payment'] = $template->cuota_inicial;
                        $options['balloon_payment'] = $template->cuota_balon;
                        $options['financing_amount'] = $template->getFinancingAmount();
                        $options['total_amount'] = $template->getTotalPaymentForInstallments($alternativeInstallments);
                    } else {
                        // Si no hay opciones de cuotas, cambiar a contado
                        $options['payment_type'] = 'cash';
                        $options['total_amount'] = $template->hasCashPrice() ? $template->precio_contado : $template->precio_venta;
                        $options['financing_amount'] = $options['total_amount'];
                    }
                }
            } else {
                // Si no hay opciones de cuotas, cambiar a contado
                $options['payment_type'] = 'cash';
                $options['total_amount'] = $template->hasCashPrice() ? $template->precio_contado : $template->precio_venta;
                $options['financing_amount'] = $options['total_amount'];
            }
        }

        return $options;
    }

    /**
     * Generar número único de AR
     */
    private function generateARNumber(): string
    {
        $lastAR = AccountReceivable::orderBy('ar_id', 'desc')->first();
        $nextNumber = $lastAR ? intval(substr($lastAR->ar_number, 3)) + 1 : 1;
        return 'AR-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Crear servicio de cuentas por cobrar desde cronogramas generados
     */
    public function createAccountsReceivableFromGeneratedSchedules(Contract $contract, array $schedules): array
    {
        $accountsReceivable = [];
        
        foreach ($schedules as $schedule) {
            // Verificar si ya existe una cuenta por cobrar para este cronograma
            $existingAR = AccountReceivable::where('contract_id', $contract->contract_id)
                ->where('due_date', $schedule->due_date)
                ->where('original_amount', $schedule->amount)
                ->first();
                
            if ($existingAR) {
                $accountsReceivable[] = $existingAR;
                continue;
            }
            
            // Obtener client_id: desde reserva o directamente del contrato
            $clientId = null;
            if ($contract->reservation && $contract->reservation->client_id) {
                $clientId = $contract->reservation->client_id;
            } elseif ($contract->client_id) {
                $clientId = $contract->client_id;
            }
            
            // Crear cuenta por cobrar para cada cronograma
            $arData = [
                'client_id' => $clientId,
                'contract_id' => $contract->contract_id,
                'ar_number' => $this->generateARNumber(),
                'issue_date' => now(),
                'due_date' => $schedule->due_date,
                'original_amount' => $schedule->amount,
                'outstanding_amount' => $schedule->amount,
                'currency' => 'DOP',
                'status' => AccountReceivable::STATUS_PENDING,
                'description' => 'Cuota #' . $schedule->installment_number . ' - Contrato ' . $contract->contract_number,
                'notes' => 'Generado automáticamente desde cronograma de pagos #' . ($schedule->schedule_id ?? 'nuevo')
            ];
            
            $accountReceivable = AccountReceivable::create($arData);
            $accountsReceivable[] = $accountReceivable;
        }
        
        return $accountsReceivable;
    }

    /**
     * Obtiene estadísticas de generación de cronogramas
     */
    public function getGenerationStats(): array
    {
        $contractsWithSchedule = Contract::whereHas('paymentSchedules')->count();
        $contractsWithoutSchedule = Contract::where('status', 'active')
            ->whereDoesntHave('paymentSchedules')
            ->count();
        $totalActiveContracts = Contract::where('status', 'active')->count();

        return [
            'total_active_contracts' => $totalActiveContracts,
            'contracts_with_schedule' => $contractsWithSchedule,
            'contracts_without_schedule' => $contractsWithoutSchedule,
            'completion_percentage' => $totalActiveContracts > 0 
                ? round(($contractsWithSchedule / $totalActiveContracts) * 100, 2) 
                : 0
        ];
    }
}