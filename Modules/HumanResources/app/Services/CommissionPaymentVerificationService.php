<?php

namespace Modules\HumanResources\Services;

use App\Models\CommissionPaymentVerification;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Models\CustomerPayment;
use Modules\HumanResources\Models\Commission;

class CommissionPaymentVerificationService
{
    /**
     * Verifica automáticamente los pagos del cliente para una comisión específica
     * Considera el payment_part para comisiones divididas
     */
    public function verifyClientPayments(Commission $commission): array
    {
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
                $results['message'] = 'La comisión no requiere verificación de pagos del cliente - Marcada automáticamente como verificada';
                
                // Marcar automáticamente como verificada y elegible para pago
                $commission->update([
                    'payment_verification_status' => 'fully_verified',
                    'is_eligible_for_payment' => true,
                    'verification_notes' => 'Comisión marcada automáticamente como verificada - No requiere verificación de pagos del cliente. Verificación automática realizada el ' . now()->format('d/m/Y H:i:s')
                ]);
                
                Log::info('Comisión marcada automáticamente como verificada', [
                    'commission_id' => $commission->id,
                    'contract_id' => $commission->contract_id,
                    'reason' => 'requires_client_payment_verification = false'
                ]);
                
                DB::commit();
                return $results;
            }
            
            // Obtener las cuentas por cobrar del contrato
            $accountsReceivable = AccountReceivable::where('contract_id', $commission->contract_id)
                ->orderBy('due_date', 'asc')
                ->get();
                
            if ($accountsReceivable->count() < 2) {
                $results['message'] = 'El contrato no tiene suficientes cuotas para verificar';
                DB::commit();
                return $results;
            }
            
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
                    $results['message'] = 'Comisión dividida parte 1/2 - Solo verifica primera cuota del cliente';
                } elseif ($commission->payment_part == 2) {
                    // Solo verificar segunda cuota para payment_part = 2
                    $secondInstallment = $accountsReceivable->skip(1)->first();
                    $results['second_payment'] = $this->verifyInstallmentPayment(
                        $commission, 
                        $secondInstallment, 
                        'second'
                    );
                    $results['message'] = 'Comisión dividida parte 2/2 - Solo verifica segunda cuota del cliente';
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
                $results['message'] = 'Comisión completa - Verifica ambas cuotas del cliente';
            }
            
            // Actualizar estado de verificación de la comisión
            $this->updateCommissionVerificationStatus($commission, $results);
            
            DB::commit();
            
            Log::info('Verificación de pagos completada', [
                'commission_id' => $commission->id,
                'contract_id' => $commission->contract_id,
                'payment_part' => $commission->payment_part,
                'results' => $results
            ]);
            
            return $results;
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error en verificación de pagos', [
                'commission_id' => $commission->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Verifica el pago de una cuota específica
     */
    public function verifyInstallmentPayment(Commission $commission, AccountReceivable $installment, string $installmentType): bool
    {
        // Verificar si ya existe una verificación para esta cuota
        $existingVerification = CommissionPaymentVerification::where('commission_id', $commission->id)
            ->where('payment_installment', $installmentType)
            ->first();
            
        if ($existingVerification && $existingVerification->verification_status === 'verified') {
            return true;
        }
        
        // Buscar pagos para esta cuenta por cobrar
        $payments = CustomerPayment::where('ar_id', $installment->ar_id)
            ->where('payment_date', '<=', now())
            ->get();
            
        $totalPaid = $payments->sum('amount');
        $tolerance = 0.01; // Tolerancia de 1 centavo
        
        // Verificar si el pago es suficiente
        $isPaid = ($totalPaid >= ($installment->original_amount - $tolerance));
        
        if ($isPaid && $payments->isNotEmpty()) {
            // Crear o actualizar verificación
            $latestPayment = $payments->sortByDesc('payment_date')->first();
            
            CommissionPaymentVerification::updateOrCreate(
                [
                    'commission_id' => $commission->id,
                    'payment_installment' => $installmentType
                ],
                [
                    'customer_payment_id' => $latestPayment->id,
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'verified_by' => auth()->id(),
                    'payment_amount' => $totalPaid,
                    'payment_date' => $latestPayment->payment_date,
                    'verification_notes' => "Pago verificado automáticamente. Total pagado: $totalPaid",
                    'verification_metadata' => [
                        'installment_amount' => $installment->original_amount,
                        'total_payments' => $payments->count(),
                        'verification_date' => now()->toISOString()
                    ]
                ]
            );
            
            return true;
        }
        
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
        $verifications = CommissionPaymentVerification::where('commission_id', $commission->id)
            ->where('verification_status', 'verified')
            ->get();
            
        $firstVerification = $verifications->where('payment_installment', 'first')->first();
        $secondVerification = $verifications->where('payment_installment', 'second')->first();
        
        if ($firstVerification) {
            $firstVerifiedAt = $firstVerification->verified_at;
        }
        
        if ($secondVerification) {
            $secondVerifiedAt = $secondVerification->verified_at;
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
                'verification_notes' => ($verification->verification_notes ?? '') . 
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