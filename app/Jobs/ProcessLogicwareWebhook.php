<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\WebhookLog;
use App\Services\LogicwareWebhookHandler;
use App\Services\NotificationService;

class ProcessLogicwareWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $webhookLogId;
    protected $payload;

    /**
     * Número máximo de reintentos
     */
    public $tries = 3;

    /**
     * Tiempo de espera entre reintentos (en segundos)
     */
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * Create a new job instance.
     */
    public function __construct($webhookLogId, array $payload)
    {
        $this->webhookLogId = $webhookLogId;
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $webhookLog = WebhookLog::find($this->webhookLogId);
        
        if (!$webhookLog) {
            Log::error('❌ Webhook log no encontrado', ['id' => $this->webhookLogId]);
            return;
        }

        try {
            Log::info('🔄 Procesando webhook', [
                'messageId' => $this->payload['messageId'],
                'eventType' => $this->payload['eventType'],
                'attempt' => $this->attempts()
            ]);

            // Actualizar estado a procesando
            $webhookLog->update(['status' => 'processing']);

            // Procesar webhook según el tipo de evento
            $handler = new LogicwareWebhookHandler();
            $result = $handler->handle($this->payload);

            // Marcar como procesado exitosamente
            $webhookLog->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null
            ]);

            // Enviar notificación al sistema
            $this->sendNotification($result);

            Log::info('✅ Webhook procesado exitosamente', [
                'messageId' => $this->payload['messageId'],
                'eventType' => $this->payload['eventType'],
                'result' => $result
            ]);

        } catch (\Exception $e) {
            $webhookLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'retry_count' => $this->attempts()
            ]);

            Log::error('❌ Error al procesar webhook', [
                'messageId' => $this->payload['messageId'],
                'eventType' => $this->payload['eventType'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts()
            ]);

            // Re-lanzar excepción para que Laravel maneje los reintentos
            throw $e;
        }
    }

    /**
     * Manejar fallo del job después de todos los reintentos
     */
    public function failed(\Throwable $exception)
    {
        $webhookLog = WebhookLog::find($this->webhookLogId);
        
        if ($webhookLog) {
            $webhookLog->update([
                'status' => 'failed_permanently',
                'error_message' => 'Falló después de ' . $this->tries . ' intentos: ' . $exception->getMessage()
            ]);
        }

        Log::critical('🔴 Webhook falló permanentemente', [
            'messageId' => $this->payload['messageId'] ?? 'unknown',
            'eventType' => $this->payload['eventType'] ?? 'unknown',
            'error' => $exception->getMessage(),
            'attempts' => $this->tries
        ]);

        // Enviar notificación de error crítico
        $this->sendErrorNotification($exception);
    }

    /**
     * Enviar notificación al sistema sobre el cambio
     */
    private function sendNotification(array $result)
    {
        try {
            // TODO: Implementar sistema de notificaciones para webhooks
            // Por ahora solo loggeamos el resultado
            Log::info('📬 Webhook procesado exitosamente - Notificación pendiente', [
                'messageId' => $this->payload['messageId'],
                'eventType' => $this->payload['eventType'],
                'result' => $result
            ]);
            
            // Cuando se implemente el broadcast, descomentar:
            // $notificationService = new NotificationService();
            // $message = $this->buildNotificationMessage($result);
            // $type = $this->getNotificationType($this->payload['eventType']);
            // $notificationService->broadcastWebhookNotification([...]);

        } catch (\Exception $e) {
            // No fallar el job si la notificación falla
            Log::warning('⚠️ Error al enviar notificación de webhook', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Construir mensaje de notificación según el evento
     */
    private function buildNotificationMessage(array $result): string
    {
        $eventType = $this->payload['eventType'];
        
        switch ($eventType) {
            case 'sales.process.completed':
                return "Nueva venta completada: Contrato {$result['contract_number']} - {$result['client_name']}";
            
            case 'separation.process.completed':
                return "Separación completada: Contrato {$result['contract_number']} - {$result['client_name']}";
            
            case 'payment.created':
                return "Nuevo pago registrado: {$result['amount']} en contrato {$result['contract_number']}";
            
            case 'schedule.created':
                return "Cronograma creado/actualizado para contrato {$result['contract_number']}";
            
            case 'unit.updated':
                return "Lote actualizado: {$result['unit_code']} - Estado: {$result['status']}";
            
            default:
                return "Evento recibido desde Logicware: {$eventType}";
        }
    }

    /**
     * Obtener tipo de notificación según el evento
     */
    private function getNotificationType(string $eventType): string
    {
        if (str_contains($eventType, 'completed') || str_contains($eventType, 'created')) {
            return 'success';
        }
        
        if (str_contains($eventType, 'updated')) {
            return 'info';
        }
        
        if (str_contains($eventType, 'refund') || str_contains($eventType, 'cancelled')) {
            return 'warning';
        }
        
        return 'info';
    }

    /**
     * Enviar notificación de error crítico
     */
    private function sendErrorNotification(\Throwable $exception)
    {
        try {
            $notificationService = new NotificationService();
            
            $notificationService->broadcastWebhookNotification([
                'messageId' => $this->payload['messageId'] ?? 'unknown',
                'eventType' => $this->payload['eventType'] ?? 'unknown',
                'message' => "Error crítico procesando webhook: {$exception->getMessage()}",
                'type' => 'error',
                'error' => true
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error al enviar notificación de error', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
