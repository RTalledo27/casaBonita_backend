<?php

namespace Modules\Collections\Services;

use Modules\Collections\Models\CustomerPayment;
use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Events\InstallmentPaidEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class PaymentDetectionService
{
    /**
     * Detecta el tipo de cuota pagada por el cliente
     */
    public function detectInstallmentType(CustomerPayment $payment): string
    {
        try {
            if (!$payment->accountReceivable || !$payment->accountReceivable->contract_id) {
                Log::info('Payment does not have contract, marking as regular', [
                    'payment_id' => $payment->payment_id
                ]);
                return 'regular';
            }

            $contractId = $payment->accountReceivable->contract_id;
            $paymentDate = Carbon::parse($payment->payment_date);
            
            // Obtener pagos anteriores del contrato que afectan comisiones
            $previousPayments = CustomerPayment::whereHas('accountReceivable', function($query) use ($contractId) {
                $query->where('contract_id', $contractId);
            })
            ->where('payment_date', '<', $paymentDate)
            ->where('affects_commissions', true)
            ->orderBy('payment_date')
            ->get();
            
            $paymentCount = $previousPayments->count();
            
            // Lógica de detección basada en el orden de pagos
            if ($paymentCount === 0) {
                Log::info('First commission-affecting payment detected', [
                    'payment_id' => $payment->payment_id,
                    'contract_id' => $contractId
                ]);
                return 'first';
            } elseif ($paymentCount === 1) {
                Log::info('Second commission-affecting payment detected', [
                    'payment_id' => $payment->payment_id,
                    'contract_id' => $contractId
                ]);
                return 'second';
            }
            
            Log::info('Regular payment detected (beyond second)', [
                'payment_id' => $payment->payment_id,
                'contract_id' => $contractId,
                'previous_payments_count' => $paymentCount
            ]);
            
            return 'regular';
            
        } catch (Exception $e) {
            Log::error('Error detecting installment type', [
                'payment_id' => $payment->payment_id,
                'error' => $e->getMessage()
            ]);
            
            return 'unknown';
        }
    }
    
    /**
     * Procesa un pago y dispara eventos si afecta comisiones
     */
    public function processPaymentForCommissions(CustomerPayment $payment): void
    {
        try {
            DB::beginTransaction();
            
            // Detectar tipo de cuota
            $installmentType = $this->detectInstallmentType($payment);
            
            // Validar criterios de pago
            $meetsPaymentCriteria = $this->validatePaymentCriteria($payment);
            
            // Determinar si afecta comisiones
            $affectsCommissions = in_array($installmentType, ['first', 'second']) && $meetsPaymentCriteria;
            
            // Actualizar el pago con la información detectada
            $payment->update([
                'installment_type' => $installmentType,
                'installment_detection_method' => 'automatic',
                'affects_commissions' => $affectsCommissions,
                'installment_detected_at' => now(),
                'installment_detected_by' => auth()->id(),
                'installment_detection_notes' => $this->generateDetectionNotes($installmentType, $meetsPaymentCriteria)
            ]);
            
            // Disparar evento si afecta comisiones y no se ha disparado antes
            if ($affectsCommissions && !$payment->commission_event_dispatched) {
                $this->dispatchCommissionEvent($payment, $installmentType);
            }
            
            DB::commit();
            
            Log::info('Payment processed for commissions', [
                'payment_id' => $payment->payment_id,
                'installment_type' => $installmentType,
                'affects_commissions' => $affectsCommissions,
                'meets_criteria' => $meetsPaymentCriteria
            ]);
            
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error processing payment for commissions', [
                'payment_id' => $payment->payment_id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Verifica si un pago cumple los criterios mínimos para afectar comisiones
     */
    public function validatePaymentCriteria(CustomerPayment $payment): bool
    {
        $accountReceivable = $payment->accountReceivable;
        
        if (!$accountReceivable) {
            Log::warning('Payment has no account receivable', [
                'payment_id' => $payment->payment_id
            ]);
            return false;
        }
        
        // Verificar monto mínimo (90% de la cuota)
        $minimumAmount = $accountReceivable->original_amount * 0.9;
        
        if ($payment->amount < $minimumAmount) {
            Log::info('Payment amount below minimum threshold', [
                'payment_id' => $payment->payment_id,
                'payment_amount' => $payment->amount,
                'minimum_required' => $minimumAmount,
                'original_amount' => $accountReceivable->original_amount
            ]);
            return false;
        }
        
        // Verificar tolerancia de fechas (5 días después del vencimiento)
        $dueDate = Carbon::parse($accountReceivable->due_date);
        $paymentDate = Carbon::parse($payment->payment_date);
        $gracePeriodEnd = $dueDate->copy()->addDays(5);
        
        if ($paymentDate->gt($gracePeriodEnd)) {
            Log::info('Payment outside grace period', [
                'payment_id' => $payment->payment_id,
                'payment_date' => $paymentDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'grace_period_end' => $gracePeriodEnd->toDateString()
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Dispara el evento de comisión para el pago
     */
    private function dispatchCommissionEvent(CustomerPayment $payment, string $installmentType): void
    {
        try {
            $event = new InstallmentPaidEvent($payment, $installmentType, [
                'detection_method' => 'automatic',
                'validation_passed' => true,
                'detected_at' => now()->toISOString()
            ]);
            
            Event::dispatch($event);
            
            // Actualizar el pago para marcar que el evento fue disparado
            $payment->update([
                'commission_event_dispatched' => true,
                'commission_event_id' => $event->id
            ]);
            
            Log::info('Commission event dispatched', [
                'payment_id' => $payment->payment_id,
                'event_id' => $event->id,
                'installment_type' => $installmentType
            ]);
            
        } catch (Exception $e) {
            Log::error('Error dispatching commission event', [
                'payment_id' => $payment->payment_id,
                'installment_type' => $installmentType,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Genera notas de detección para el pago
     */
    private function generateDetectionNotes(string $installmentType, bool $meetsPaymentCriteria): string
    {
        $notes = "Tipo de cuota detectado automáticamente: {$installmentType}.";
        
        if ($meetsPaymentCriteria) {
            $notes .= " Cumple criterios para afectar comisiones.";
        } else {
            $notes .= " No cumple criterios para afectar comisiones (monto insuficiente o fuera del período de gracia).";
        }
        
        return $notes;
    }
    
    /**
     * Redetecta el tipo de cuota para un pago específico (uso manual)
     */
    public function redetectInstallmentType(CustomerPayment $payment, ?int $userId = null): string
    {
        try {
            DB::beginTransaction();
            
            $oldType = $payment->installment_type;
            $newType = $this->detectInstallmentType($payment);
            $meetsPaymentCriteria = $this->validatePaymentCriteria($payment);
            $affectsCommissions = in_array($newType, ['first', 'second']) && $meetsPaymentCriteria;
            
            $payment->update([
                'installment_type' => $newType,
                'installment_detection_method' => 'manual',
                'affects_commissions' => $affectsCommissions,
                'installment_detected_at' => now(),
                'installment_detected_by' => $userId ?? auth()->id(),
                'installment_detection_notes' => "Redetección manual. Tipo anterior: {$oldType}, nuevo tipo: {$newType}. " .
                                               $this->generateDetectionNotes($newType, $meetsPaymentCriteria)
            ]);
            
            // Si cambió a un tipo que afecta comisiones y no se había disparado evento
            if ($affectsCommissions && !$payment->commission_event_dispatched && $oldType !== $newType) {
                $this->dispatchCommissionEvent($payment, $newType);
            }
            
            DB::commit();
            
            Log::info('Installment type redetected', [
                'payment_id' => $payment->payment_id,
                'old_type' => $oldType,
                'new_type' => $newType,
                'user_id' => $userId ?? auth()->id()
            ]);
            
            return $newType;
            
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error redetecting installment type', [
                'payment_id' => $payment->payment_id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Obtiene estadísticas de detección de cuotas
     */
    public function getDetectionStats(array $filters = []): array
    {
        $query = CustomerPayment::query();
        
        if (isset($filters['date_from'])) {
            $query->where('payment_date', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('payment_date', '<=', $filters['date_to']);
        }
        
        if (isset($filters['contract_id'])) {
            $query->whereHas('accountReceivable', function($q) use ($filters) {
                $q->where('contract_id', $filters['contract_id']);
            });
        }
        
        return [
            'total_payments' => $query->count(),
            'affects_commissions' => $query->where('affects_commissions', true)->count(),
            'by_installment_type' => $query->groupBy('installment_type')
                                          ->selectRaw('installment_type, count(*) as count')
                                          ->pluck('count', 'installment_type')
                                          ->toArray(),
            'by_detection_method' => $query->groupBy('installment_detection_method')
                                          ->selectRaw('installment_detection_method, count(*) as count')
                                          ->pluck('count', 'installment_detection_method')
                                          ->toArray(),
            'events_dispatched' => $query->where('commission_event_dispatched', true)->count()
        ];
    }
}