<?php

namespace Modules\HumanResources\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Modules\Collections\Events\InstallmentPaidEvent;
use Modules\HumanResources\Services\CommissionVerificationService;
use App\Models\PaymentEvent;
use Exception;

class CommissionVerificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $maxExceptions = 3;
    public int $timeout = 120;
    public int $backoff = 30;

    /**
     * Create the event listener.
     */
    public function __construct(
        private CommissionVerificationService $commissionVerificationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(InstallmentPaidEvent $event): void
    {
        try {
            $contractId = $event->getContractId();
            
            Log::info('Commission verification listener triggered', [
                'event_id' => $event->id,
                'payment_id' => $event->payment->payment_id,
                'installment_type' => $event->installmentType,
                'contract_id' => $contractId
            ]);

            // Verificar si el evento tiene contract_id válido
            if ($contractId === null) {
                Log::info('Event has no contract_id, skipping commission verification', [
                    'event_id' => $event->id,
                    'payment_id' => $event->payment->payment_id,
                    'ar_id' => $event->payment->ar_id
                ]);
                return;
            }

            // Verificar si el evento afecta comisiones
            if (!$event->affectsCommissions()) {
                Log::info('Event does not affect commissions, skipping', [
                    'event_id' => $event->id,
                    'installment_type' => $event->installmentType
                ]);
                return;
            }

            DB::beginTransaction();

            // Registrar el evento en la tabla payment_events
            $this->recordPaymentEvent($event);

            // Procesar verificación de comisiones
            $results = $this->commissionVerificationService->processCommissionVerification($event);

            DB::commit();

            Log::info('Commission verification completed successfully', [
                'event_id' => $event->id,
                'commissions_processed' => count($results),
                'results' => $results
            ]);

            // Marcar el evento como procesado
            $this->markEventAsProcessed($event->id);

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Commission verification failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Registrar el error en el evento
            $this->recordEventError($event->id, $e->getMessage());

            // Re-lanzar la excepción para que Laravel maneje los reintentos
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(InstallmentPaidEvent $event, Exception $exception): void
    {
        Log::error('Commission verification listener failed permanently', [
            'event_id' => $event->id,
            'payment_id' => $event->payment->payment_id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts
        ]);

        // Marcar el evento como fallido
        $this->recordEventError($event->id, $exception->getMessage(), true);

        // Aquí podrías enviar una notificación a los administradores
        // o crear una tarea manual para revisar el problema
    }

    /**
     * Registra el evento en la tabla payment_events.
     */
    private function recordPaymentEvent(InstallmentPaidEvent $event): void
    {
        $contractId = $event->getContractId();
        
        // Solo registrar eventos con contract_id válido
        if ($contractId === null) {
            Log::warning('Skipping payment event recording due to null contract_id', [
                'event_id' => $event->id,
                'payment_id' => $event->payment->payment_id
            ]);
            return;
        }
        
        PaymentEvent::create([
            'id' => $event->id,
            'event_type' => 'installment_paid',
            'payment_id' => $event->payment->payment_id,
            'contract_id' => $contractId,
            'installment_type' => $event->installmentType,
            'event_data' => $event->getEventData(),
            'triggered_by' => auth()->id(),
            'processed' => false
        ]);
    }

    /**
     * Marca el evento como procesado exitosamente.
     */
    private function markEventAsProcessed(string $eventId): void
    {
        PaymentEvent::where('id', $eventId)->update([
            'processed' => true,
            'processed_at' => now(),
            'error_message' => null
        ]);
    }

    /**
     * Registra un error en el procesamiento del evento.
     */
    private function recordEventError(string $eventId, string $errorMessage, bool $isFinalFailure = false): void
    {
        $updateData = [
            'error_message' => $errorMessage,
            'last_retry_at' => now()
        ];

        if (!$isFinalFailure) {
            $updateData['retry_count'] = DB::raw('retry_count + 1');
        }

        PaymentEvent::where('id', $eventId)->update($updateData);
    }

    /**
     * Determine the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // Esperar 30s, 60s, 120s entre reintentos
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['commission-verification', 'payment-events'];
    }
}