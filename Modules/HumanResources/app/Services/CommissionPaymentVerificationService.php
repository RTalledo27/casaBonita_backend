<?php

namespace Modules\HumanResources\app\Services;

use App\Models\CommissionPaymentVerification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Models\CustomerPayment;
use Modules\HumanResources\Models\Commission;
use Modules\Sales\Models\PaymentSchedule;

class CommissionPaymentVerificationService
{
    /**
     * Verifica automáticamente los pagos del cliente para una comisión específica
     * Considera el payment_part para comisiones divididas
     */
    public function verifyClientPayments(Commission $commission): array
    {
        Log::info('=== INICIO VERIFICACIÓN DE PAGOS DEL CLIENTE ===', [
            'commission_id' => $commission->commission_id,
            'contract_id' => $commission->contract_id,
            'requires_verification' => $commission->requires_client_payment_verification,
            'timestamp' => now()->toDateTimeString()
        ]);

        try {
            DB::beginTransaction();
            
            $results = [
                'first_payment' => false,
                'second_payment' => false,
                'verification_details' => [],
                'payment_part' => $commission->payment_part
            ];
            
            // Si la comisión no requiere verificación, marcarla automáticamente como verificada
            if (!$commission->requires_client_payment_verification) {
                Log::info('DEBUG: Comisión no requiere verificación - retornando true para ambos pagos', [
                    'commission_id' => $commission->commission_id,
                    'tabla_consultada' => 'commissions',
                    'campo_verificado' => 'requires_client_payment_verification = false'
                ]);
                $results['message'] = 'La comisión no requiere verificación de pagos del cliente - Marcada automáticamente como verificada';
                
                // Marcar automáticamente como verificada y elegible para pago
                Log::info('DEBUG: Actualizando tabla commissions con verificación automática', [
                    'commission_id' => $commission->commission_id,
                    'campos_a_actualizar' => [
                        'payment_verification_status' => 'fully_verified',
                        'is_eligible_for_payment' => true
                    ]
                ]);
                
                $commission->update([
                    'payment_verification_status' => 'fully_verified',
                    'is_eligible_for_payment' => true,
                    'verification_notes' => 'Comisión marcada automáticamente como verificada - No requiere verificación de pagos del cliente. Verificación automática realizada el ' . now()->format('d/m/Y H:i:s')
                ]);
                
                Log::info('DEBUG: Comisión marcada automáticamente como verificada', [
                    'commission_id' => $commission->id,
                    'contract_id' => $commission->contract_id,
                    'reason' => 'requires_client_payment_verification = false',
                    'tabla_actualizada' => 'commissions'
                ]);
                
                DB::commit();
                return $results;
            }
            
            Log::info('DEBUG: Buscando cronograma de pagos', [
                'commission_id' => $commission->commission_id,
                'contract_id' => $commission->contract_id,
                'query_a_ejecutar' => 'SELECT * FROM payment_schedules WHERE contract_id = ' . $commission->contract_id
            ]);

            // Detectar si el contrato tiene cronograma de pagos (PaymentSchedule)
            $hasPaymentSchedule = PaymentSchedule::where('contract_id', $commission->contract_id)->exists();
            
            Log::info('DEBUG: Consultando cuentas por cobrar', [
                'contract_id' => $commission->contract_id,
                'query_a_ejecutar' => 'SELECT * FROM accounts_receivable WHERE contract_id = ' . $commission->contract_id . ' ORDER BY due_date ASC'
            ]);
            
            // Obtener las cuentas por cobrar del contrato
            $accountsReceivable = AccountReceivable::where('contract_id', $commission->contract_id)
                ->orderBy('due_date', 'asc')
                ->get();

            Log::info('DEBUG: Información del contrato para verificación', [
                'contract_id' => $commission->contract_id,
                'has_payment_schedule' => $hasPaymentSchedule,
                'accounts_receivable_count' => $accountsReceivable->count(),
                'payment_part' => $commission->payment_part,
                'tablas_consultadas' => ['payment_schedules', 'accounts_receivable']
            ]);
                
            if ($accountsReceivable->count() < 1) {
                $results['message'] = 'El contrato no tiene cuentas por cobrar para verificar';
                DB::commit();
                return $results;
            }
            
            // Aplicar lógica diferente según si hay cronograma de pagos o no
            if ($hasPaymentSchedule) {
                // LÓGICA PARA CONTRATOS CON CRONOGRAMA DE PAGOS
                Log::info('DEBUG: Ejecutando verificación CON cronograma de pagos');
                $results = $this->verifyPaymentsWithSchedule($commission, $results);
            } else {
                // LÓGICA PARA CONTRATOS SIN CRONOGRAMA (LÓGICA ORIGINAL)
                Log::info('DEBUG: Ejecutando verificación SIN cronograma de pagos');
                if ($accountsReceivable->count() < 2) {
                    $results['message'] = 'El contrato sin cronograma no tiene suficientes cuotas para verificar (mínimo 2)';
                    DB::commit();
                    return $results;
                }
                
                $results = $this->verifyPaymentsWithoutSchedule($commission, $accountsReceivable, $results);
            }
            
            // Actualizar estado de verificación de la comisión
            Log::info('DEBUG: Actualizando estado de verificación de la comisión', [
                'commission_id' => $commission->commission_id,
                'tabla_a_actualizar' => 'commissions'
            ]);
            $this->updateCommissionVerificationStatus($commission, $results);
            
            DB::commit();
            
            Log::info('=== VERIFICACIÓN DE PAGOS COMPLETADA EXITOSAMENTE ===', [
                'commission_id' => $commission->id,
                'contract_id' => $commission->contract_id,
                'payment_part' => $commission->payment_part,
                'results' => $results,
                'timestamp' => now()->toDateTimeString()
            ]);
            
            return $results;
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('=== ERROR EN VERIFICACIÓN DE PAGOS ===', [
                'commission_id' => $commission->id,
                'error' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'timestamp' => now()->toDateTimeString()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Verifica pagos para contratos CON cronograma de pagos (PaymentSchedule)
     */
    private function verifyPaymentsWithSchedule(Commission $commission, array $results): array
    {
        Log::info('=== VERIFICACIÓN CON CRONOGRAMA DE PAGOS ===', [
            'commission_id' => $commission->commission_id,
            'contract_id' => $commission->contract_id,
            'payment_part' => $commission->payment_part
        ]);

        try {
            if ($commission->payment_part) {
                if ($commission->payment_part == 1) {
                    Log::info('DEBUG: Verificando cuota 1 para comisión parte 1', [
                        'commission_id' => $commission->commission_id,
                        'contract_id' => $commission->contract_id
                    ]);
                    
                    // Buscar la primera cuota del cronograma
                    Log::info('DEBUG: Consultando tabla payment_schedules para primera cuota', [
                        'query' => 'SELECT * FROM payment_schedules WHERE contract_id = ' . $commission->contract_id . ' ORDER BY installment_number ASC LIMIT 1',
                        'tabla' => 'payment_schedules',
                        'campos_filtro' => ['contract_id' => $commission->contract_id]
                    ]);
                    
                    $firstSchedule = \Modules\Collections\Models\PaymentSchedule::where('contract_id', $commission->contract_id)
                        ->orderBy('installment_number', 'asc')
                        ->first();
                    
                    Log::info('DEBUG: Resultado consulta payment_schedules', [
                        'found' => !is_null($firstSchedule),
                        'schedule_data' => $firstSchedule ? [
                            'installment_number' => $firstSchedule->installment_number,
                            'due_date' => $firstSchedule->due_date,
                            'amount' => $firstSchedule->amount
                        ] : null
                    ]);
                    
                    if (!$firstSchedule) {
                        $results['message'] = 'No se encontró la primera cuota del cronograma';
                        return $results;
                    }
                    
                    // Buscar la AccountReceivable correspondiente a esta cuota
                    Log::info('DEBUG: Consultando tabla accounts_receivable para primera cuota', [
                        'query' => 'SELECT * FROM accounts_receivable WHERE contract_id = ' . $commission->contract_id . ' AND due_date = "' . $firstSchedule->due_date . '" AND original_amount = ' . $firstSchedule->amount,
                        'tabla' => 'accounts_receivable',
                        'campos_filtro' => [
                            'contract_id' => $commission->contract_id,
                            'due_date' => $firstSchedule->due_date,
                            'original_amount' => $firstSchedule->amount
                        ]
                    ]);
                    
                    $firstInstallment = AccountReceivable::where('contract_id', $commission->contract_id)
                        ->where('due_date', $firstSchedule->due_date)
                        ->where('original_amount', $firstSchedule->amount)
                        ->first();
                    
                    Log::info('DEBUG: Resultado consulta accounts_receivable', [
                        'found' => !is_null($firstInstallment),
                        'installment_data' => $firstInstallment ? [
                            'id' => $firstInstallment->id,
                            'due_date' => $firstInstallment->due_date,
                            'original_amount' => $firstInstallment->original_amount,
                            'paid_amount' => $firstInstallment->paid_amount,
                            'status' => $firstInstallment->status
                        ] : null
                    ]);
                    
                    if (!$firstInstallment) {
                        $results['message'] = 'No se encontró la cuenta por cobrar para la primera cuota del cronograma';
                        return $results;
                    }
                    
                    $results['first_payment'] = $this->verifyInstallmentPayment(
                        $commission, 
                        $firstInstallment, 
                        'first'
                    );
                    $results['message'] = 'Comisión dividida parte 1/2 - Verifica primera cuota del cronograma';
                    
                    Log::info('DEBUG: Resultado verificación cuota 1', [
                        'commission_id' => $commission->commission_id,
                        'first_payment_verified' => $results['first_payment'],
                        'message' => $results['message']
                    ]);
                    
                } elseif ($commission->payment_part == 2) {
                    Log::info('Verificando cuota 2 para comisión parte 2', [
                        'commission_id' => $commission->commission_id,
                        'contract_id' => $commission->contract_id
                    ]);
                    
                    // Buscar la segunda cuota del cronograma
                    $secondSchedule = \Modules\Collections\Models\PaymentSchedule::where('contract_id', $commission->contract_id)
                        ->orderBy('installment_number', 'asc')
                        ->skip(1)
                        ->first();
                    
                    if (!$secondSchedule) {
                        $results['message'] = 'No se encontró la segunda cuota del cronograma';
                        return $results;
                    }
                    
                    // Buscar la AccountReceivable correspondiente a esta cuota
                    $secondInstallment = AccountReceivable::where('contract_id', $commission->contract_id)
                        ->where('due_date', $secondSchedule->due_date)
                        ->where('original_amount', $secondSchedule->amount)
                        ->first();
                    
                    if (!$secondInstallment) {
                        $results['message'] = 'No se encontró la cuenta por cobrar para la segunda cuota del cronograma';
                        return $results;
                    }
                    
                    $results['second_payment'] = $this->verifyInstallmentPayment(
                        $commission, 
                        $secondInstallment, 
                        'second'
                    );
                    $results['message'] = 'Comisión dividida parte 2/2 - Verifica segunda cuota del cronograma';
                    
                    Log::info('Resultado verificación cuota 2', [
                        'commission_id' => $commission->commission_id,
                        'second_payment_verified' => $results['second_payment'],
                        'message' => $results['message']
                    ]);
                }
            } else {
                // Para comisiones no divididas con cronograma, verificar las primeras dos cuotas
                $schedules = \Modules\Collections\Models\PaymentSchedule::where('contract_id', $commission->contract_id)
                    ->orderBy('installment_number', 'asc')
                    ->take(2)
                    ->get();
                
                if ($schedules->count() < 2) {
                    $results['message'] = 'El cronograma no tiene suficientes cuotas para verificar (mínimo 2)';
                    return $results;
                }
                
                // Verificar primera cuota del cronograma
                $firstSchedule = $schedules->first();
                $firstInstallment = AccountReceivable::where('contract_id', $commission->contract_id)
                    ->where('due_date', $firstSchedule->due_date)
                    ->where('original_amount', $firstSchedule->amount)
                    ->first();
                
                if ($firstInstallment) {
                    $results['first_payment'] = $this->verifyInstallmentPayment(
                        $commission, 
                        $firstInstallment, 
                        'first'
                    );
                }
                
                // Verificar segunda cuota del cronograma
                $secondSchedule = $schedules->skip(1)->first();
                $secondInstallment = AccountReceivable::where('contract_id', $commission->contract_id)
                    ->where('due_date', $secondSchedule->due_date)
                    ->where('original_amount', $secondSchedule->amount)
                    ->first();
                
                if ($secondInstallment) {
                    $results['second_payment'] = $this->verifyInstallmentPayment(
                        $commission, 
                        $secondInstallment, 
                        'second'
                    );
                }
                
                $results['message'] = 'Comisión completa con cronograma - Verifica primeras dos cuotas del cronograma';
            }
            
            Log::info('=== RESULTADO FINAL VERIFICACIÓN CON CRONOGRAMA ===', [
                'commission_id' => $commission->commission_id,
                'results' => $results
            ]);
            
            return $results;
            
        } catch (Exception $e) {
            Log::error('Error en verificación con cronograma', [
                'commission_id' => $commission->id,
                'contract_id' => $commission->contract_id,
                'error' => $e->getMessage()
            ]);
            
            $results['message'] = 'Error al verificar pagos con cronograma: ' . $e->getMessage();
            return $results;
        }
    }
    
    /**
     * Verifica pagos para contratos SIN cronograma de pagos (lógica original)
     */
    private function verifyPaymentsWithoutSchedule(Commission $commission, $accountsReceivable, array $results): array
    {
        // Para comisiones divididas, solo verificar la cuota correspondiente al payment_part
        if ($commission->payment_part) {
            if ($commission->payment_part == 1) {
                // Solo verificar primera cuota para payment_part = 1
                $firstInstallment = $accountsReceivable->first();
                $results['first_payment'] = $this->verifyInstallmentPayment(
                    $commission, 
                    $firstInstallment, 
                    'first'
                );
                $results['message'] = 'Comisión dividida parte 1/2 - Solo verifica primera cuota del cliente (sin cronograma)';
            } elseif ($commission->payment_part == 2) {
                // Solo verificar segunda cuota para payment_part = 2
                $secondInstallment = $accountsReceivable->skip(1)->first();
                $results['second_payment'] = $this->verifyInstallmentPayment(
                    $commission, 
                    $secondInstallment, 
                    'second'
                );
                $results['message'] = 'Comisión dividida parte 2/2 - Solo verifica segunda cuota del cliente (sin cronograma)';
            }
        } else {
            // Para comisiones no divididas, verificar ambas cuotas
            $firstInstallment = $accountsReceivable->first();
            $results['first_payment'] = $this->verifyInstallmentPayment(
                $commission, 
                $firstInstallment, 
                'first'
            );
            
            $secondInstallment = $accountsReceivable->skip(1)->first();
            $results['second_payment'] = $this->verifyInstallmentPayment(
                $commission, 
                $secondInstallment, 
                'second'
            );
            $results['message'] = 'Comisión completa - Verifica ambas cuotas del cliente (sin cronograma)';
        }
        
        return $results;
    }
    
    /**
     * Verifica el pago de una cuota específica
     */
    public function verifyInstallmentPayment(Commission $commission, AccountReceivable $installment, string $installmentType): bool
    {
        Log::info('=== VERIFICANDO CUOTA ESPECÍFICA ===', [
            'commission_id' => $commission->commission_id,
            'contract_id' => $commission->contract_id,
            'installment_type' => $installmentType,
            'ar_id' => $installment->ar_id
        ]);

        // Verificar si ya existe una verificación para esta cuota
        $existingVerification = CommissionPaymentVerification::where('commission_id', $commission->id)
            ->where('payment_installment', $installmentType)
            ->first();
            
        if ($existingVerification && $existingVerification->verification_status === 'verified') {
            Log::info('Verificación ya existe y está verificada', [
                'commission_id' => $commission->commission_id,
                'installment_type' => $installmentType,
                'existing_verification_id' => $existingVerification->id
            ]);
            return true;
        }
        
        // PRIMERA VERIFICACIÓN: Revisar el estado de la cuenta por cobrar
        // Si está marcada como PAID, considerarla como pagada
        Log::info('Verificando estado de AccountReceivable', [
            'commission_id' => $commission->commission_id,
            'ar_id' => $installment->ar_id,
            'ar_status' => $installment->status,
            'ar_amount' => $installment->original_amount
        ]);

        if ($installment->status === 'PAID') {
            // Crear verificación basada en el estado de AccountReceivable
            CommissionPaymentVerification::updateOrCreate(
                [
                    'commission_id' => $commission->commission_id,
                    'payment_installment' => $installmentType
                ],
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'client_payment_id' => null, // No hay pago específico
                    'account_receivable_id' => $installment->ar_id,
                    'verification_status' => 'verified',
                    'verification_date' => now(),
                    'verified_by' => auth()->id(),
                    'verified_amount' => $installment->original_amount,
                    'notes' => "Pago verificado por estado de AccountReceivable (PAID). AR ID: {$installment->ar_id}. Método: account_receivable_status"
                ]
            );
            
            Log::info('Cuota verificada por estado de AccountReceivable', [
                'commission_id' => $commission->commission_id,
                'ar_id' => $installment->ar_id,
                'installment_type' => $installmentType,
                'ar_status' => $installment->status
            ]);
            
            return true;
        }
        
        // SEGUNDA VERIFICACIÓN: Buscar pagos registrados en CustomerPayment
        Log::info('DEBUG: Consultando tabla customer_payments', [
            'commission_id' => $commission->commission_id,
            'ar_id' => $installment->ar_id,
            'query' => 'SELECT * FROM customer_payments WHERE ar_id = ' . $installment->ar_id . ' AND payment_date <= "' . now()->toDateString() . '"',
            'tabla' => 'customer_payments',
            'campos_filtro' => [
                'ar_id' => $installment->ar_id,
                'payment_date' => '<= ' . now()->toDateString()
            ]
        ]);

        $payments = CustomerPayment::where('ar_id', $installment->ar_id)
            ->where('payment_date', '<=', now())
            ->get();
            
        $totalPaid = $payments->sum('amount');
        $tolerance = 0.01; // Tolerancia de 1 centavo
        
        Log::info('DEBUG: Resultado consulta customer_payments', [
            'commission_id' => $commission->commission_id,
            'ar_id' => $installment->ar_id,
            'payments_found' => $payments->count(),
            'total_paid' => $totalPaid,
            'required_amount' => $installment->original_amount,
            'is_sufficient' => $totalPaid >= ($installment->original_amount - $tolerance),
            'payments_details' => $payments->map(function($payment) {
                return [
                    'payment_id' => $payment->payment_id,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date,
                    'payment_method' => $payment->payment_method ?? 'N/A'
                ];
            })->toArray()
        ]);
        
        // Verificar si el pago es suficiente
        $isPaid = ($totalPaid >= ($installment->original_amount - $tolerance));
        
        if ($isPaid && $payments->isNotEmpty()) {
            // Crear o actualizar verificación
            $latestPayment = $payments->sortByDesc('payment_date')->first();
            
            CommissionPaymentVerification::updateOrCreate(
                [
                    'commission_id' => $commission->commission_id,
                    'payment_installment' => $installmentType
                ],
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'client_payment_id' => $latestPayment->payment_id,
                    'account_receivable_id' => $installment->ar_id,
                    'verification_status' => 'verified',
                    'verification_date' => now(),
                    'verified_by' => auth()->id(),
                    'verified_amount' => $totalPaid,
                    'notes' => "Pago verificado automáticamente. Total pagado: $totalPaid. Cuotas: {$payments->count()}"
                ]
            );
            
            Log::info('Verificación creada exitosamente', [
                'commission_id' => $commission->commission_id,
                'installment_type' => $installmentType,
                'verified_amount' => $totalPaid
            ]);
            
            return true;
        }
        
        Log::warning('Cuota no pudo ser verificada', [
            'commission_id' => $commission->commission_id,
            'ar_id' => $installment->ar_id,
            'installment_type' => $installmentType,
            'total_paid' => $totalPaid,
            'required_amount' => $installment->original_amount,
            'payments_count' => $payments->count()
        ]);
        
        return false;
    }
    
    /**
     * Actualiza el estado de verificación de la comisión
     * Considera el payment_part para comisiones divididas
     */
    private function updateCommissionVerificationStatus(Commission $commission, array $results): void
    {
        $status = 'pending_verification';
        $isEligible = false;
        $firstVerifiedAt = null;
        $secondVerifiedAt = null;
        
        // Para comisiones divididas, solo considerar la verificación correspondiente
        if ($commission->payment_part) {
            if ($commission->payment_part == 1) {
                // Para payment_part = 1, solo importa la primera cuota
                if ($results['first_payment']) {
                    $status = 'fully_verified';
                    $isEligible = true;
                }
            } elseif ($commission->payment_part == 2) {
                // Para payment_part = 2, solo importa la segunda cuota
                if ($results['second_payment']) {
                    $status = 'fully_verified';
                    $isEligible = true;
                }
            }
        } else {
            // Para comisiones no divididas, verificar ambas cuotas como antes
            if ($results['first_payment'] && $results['second_payment']) {
                $status = 'fully_verified';
                $isEligible = true;
            } elseif ($results['first_payment']) {
                $status = 'first_payment_verified';
                // Solo elegible para pago si es first_payment o si ambos están verificados
                $isEligible = ($commission->payment_type === 'first_payment');
            }
        }
        
        // Obtener fechas de verificación
        $verifications = CommissionPaymentVerification::where('commission_id', $commission->commission_id)
            ->where('verification_status', 'verified')
            ->get();
            
        $firstVerification = $verifications->where('payment_installment', 'first')->first();
        $secondVerification = $verifications->where('payment_installment', 'second')->first();
        
        if ($firstVerification) {
            $firstVerifiedAt = $firstVerification->verification_date;
        }
        
        if ($secondVerification) {
            $secondVerifiedAt = $secondVerification->verification_date;
        }
        
        $commission->update([
            'payment_verification_status' => $status,
            'first_payment_verified_at' => $firstVerifiedAt,
            'second_payment_verified_at' => $secondVerifiedAt,
            'is_eligible_for_payment' => $isEligible,
            'verification_notes' => $this->generateVerificationNotes($results)
        ]);
    }
    
    /**
     * Genera notas de verificación basadas en los resultados
     * Considera el payment_part para comisiones divididas
     */
    private function generateVerificationNotes(array $results): string
    {
        $notes = [];
        
        // Agregar información sobre el tipo de comisión
        if (isset($results['payment_part']) && $results['payment_part']) {
            $notes[] = "Comisión dividida - Parte {$results['payment_part']}/2";
            
            if ($results['payment_part'] == 1) {
                if ($results['first_payment']) {
                    $notes[] = 'Primera cuota del cliente verificada (requerida para parte 1/2)';
                } else {
                    $notes[] = 'Primera cuota del cliente pendiente de pago (requerida para parte 1/2)';
                }
            } elseif ($results['payment_part'] == 2) {
                if ($results['second_payment']) {
                    $notes[] = 'Segunda cuota del cliente verificada (requerida para parte 2/2)';
                } else {
                    $notes[] = 'Segunda cuota del cliente pendiente de pago (requerida para parte 2/2)';
                }
            }
        } else {
            $notes[] = 'Comisión completa (no dividida)';
            
            if ($results['first_payment']) {
                $notes[] = 'Primera cuota verificada';
            } else {
                $notes[] = 'Primera cuota pendiente de pago';
            }
            
            if ($results['second_payment']) {
                $notes[] = 'Segunda cuota verificada';
            } else {
                $notes[] = 'Segunda cuota pendiente de pago';
            }
        }
        
        $notes[] = 'Verificación automática realizada el ' . now()->format('d/m/Y H:i:s');
        
        return implode('. ', $notes);
    }
    
    /**
     * Procesa verificaciones masivas para múltiples comisiones
     */
    public function processBatchVerifications(array $commissionIds): array
    {
        $results = [];
        
        foreach ($commissionIds as $commissionId) {
            try {
                $commission = Commission::findOrFail($commissionId);
                $results[$commissionId] = $this->verifyClientPayments($commission);
            } catch (Exception $e) {
                $results[$commissionId] = [
                    'error' => $e->getMessage(),
                    'first_payment' => false,
                    'second_payment' => false
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Revierte una verificación de pago
     */
    public function reversePaymentVerification(int $commissionId, string $installmentType, string $reason = null): bool
    {
        try {
            DB::beginTransaction();
            
            $verification = CommissionPaymentVerification::where('commission_id', $commissionId)
                ->where('payment_installment', $installmentType)
                ->first();
                
            if (!$verification) {
                throw new Exception('Verificación no encontrada');
            }
            
            $verification->update([
                'verification_status' => 'reversed',
                'notes' => ($verification->notes ?? '') . 
                    " | Revertido el " . now()->format('d/m/Y H:i:s') . 
                    ($reason ? ": $reason" : '')
            ]);
            
            // Actualizar estado de la comisión
            $commission = Commission::findOrFail($commissionId);
            $this->recalculateCommissionStatus($commission);
            
            DB::commit();
            return true;
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al revertir verificación', [
                'commission_id' => $commissionId,
                'installment_type' => $installmentType,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Recalcula el estado de verificación de una comisión
     * Considera el payment_part para comisiones divididas
     */
    private function recalculateCommissionStatus(Commission $commission): void
    {
        $verifications = CommissionPaymentVerification::where('commission_id', $commission->id)
            ->where('verification_status', 'verified')
            ->get();
            
        $firstVerified = $verifications->where('payment_installment', 'first')->isNotEmpty();
        $secondVerified = $verifications->where('payment_installment', 'second')->isNotEmpty();
        
        $results = [
            'first_payment' => $firstVerified,
            'second_payment' => $secondVerified,
            'payment_part' => $commission->payment_part
        ];
        
        $this->updateCommissionVerificationStatus($commission, $results);
    }
}