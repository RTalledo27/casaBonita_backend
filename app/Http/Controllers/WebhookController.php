<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessLogicwareWebhook;
use App\Models\WebhookLog;

class WebhookController extends Controller
{
    /**
     * Endpoint para recibir webhooks de Logicware
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleLogicwareWebhook(Request $request)
    {
        // Validar firma HMAC-SHA256 si está configurado un secret
        if (config('services.logicware.webhook_secret')) {
            if (!$this->validateSignature($request)) {
                Log::warning('⚠️ Webhook rechazado: firma inválida', [
                    'ip' => $request->ip(),
                    'headers' => $request->headers->all()
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        // Obtener payload del webhook
        $payload = $request->all();

        // Validar estructura básica del payload
        if (!isset($payload['messageId']) || !isset($payload['eventType'])) {
            Log::error('❌ Webhook rechazado: estructura inválida', ['payload' => $payload]);
            return response()->json(['error' => 'Invalid payload structure'], 400);
        }

        // Verificar idempotencia: no procesar el mismo messageId dos veces
        $existingLog = WebhookLog::where('message_id', $payload['messageId'])->first();
        if ($existingLog) {
            Log::info('🔄 Webhook duplicado (ya procesado)', [
                'messageId' => $payload['messageId'],
                'eventType' => $payload['eventType']
            ]);
            return response()->json(['message' => 'Already processed'], 200);
        }

        // Registrar webhook en base de datos
        try {
            $webhookLog = WebhookLog::create([
                'message_id' => $payload['messageId'],
                'event_type' => $payload['eventType'],
                'correlation_id' => $payload['correlationId'] ?? null,
                'source_id' => $payload['sourceId'] ?? null,
                'payload' => json_encode($payload),
                'status' => 'pending',
                'received_at' => now(),
                'headers' => json_encode([
                    'X-Webhook-Signature' => $request->header('X-Webhook-Signature'),
                    'X-LW-Event' => $request->header('X-LW-Event'),
                    'X-LW-Delivery' => $request->header('X-LW-Delivery'),
                    'User-Agent' => $request->header('User-Agent'),
                ])
            ]);

            // Procesar webhook de forma asíncrona usando job queue
            ProcessLogicwareWebhook::dispatch($webhookLog->id, $payload);

            Log::info('✅ Webhook recibido y encolado', [
                'messageId' => $payload['messageId'],
                'eventType' => $payload['eventType'],
                'correlationId' => $payload['correlationId'] ?? null
            ]);

            return response()->json([
                'message' => 'Webhook received successfully',
                'messageId' => $payload['messageId']
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Error al procesar webhook', [
                'error' => $e->getMessage(),
                'messageId' => $payload['messageId'] ?? null
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Validar firma HMAC-SHA256 del webhook
     * 
     * @param Request $request
     * @return bool
     */
    private function validateSignature(Request $request): bool
    {
        $signature = $request->header('X-Webhook-Signature');
        
        if (!$signature) {
            return false;
        }

        // La firma viene como "sha256=<hash_hex>"
        if (!str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expectedHash = substr($signature, 7); // Quitar "sha256="
        $secret = config('services.logicware.webhook_secret');
        $payload = $request->getContent(); // Cuerpo crudo del request

        // Calcular HMAC-SHA256
        $calculatedHash = hash_hmac('sha256', $payload, $secret);

        // Comparación en tiempo constante para evitar timing attacks
        return hash_equals($calculatedHash, $expectedHash);
    }

    /**
     * Ver logs de webhooks recibidos (para debugging)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLogs(Request $request)
    {
        $logs = WebhookLog::orderBy('received_at', 'desc')
            ->limit($request->get('limit', 50))
            ->get();

        return response()->json([
            'logs' => $logs,
            'count' => $logs->count()
        ]);
    }

    /**
     * Ver detalles de un webhook específico
     * 
     * @param string $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLogDetail($messageId)
    {
        $log = WebhookLog::where('message_id', $messageId)->firstOrFail();

        return response()->json([
            'log' => $log,
            'payload' => json_decode($log->payload),
            'headers' => json_decode($log->headers)
        ]);
    }
}
