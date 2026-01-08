<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppService
{
    protected MetaWhatsAppClient $metaClient;
    protected ClicklabClient $clicklabClient;
    protected bool $preferMeta;

    public function __construct()
    {
        $this->metaClient = app(MetaWhatsAppClient::class);
        $this->clicklabClient = app(ClicklabClient::class);
        $this->preferMeta = config('meta.whatsapp_prefer_meta', true);
    }

    /**
     * Enviar mensaje de texto por WhatsApp con fallback automático
     * Intenta Meta primero, si falla usa Clicklab
     */
    public function sendText(string $to, string $text): array
    {
        // Intentar Meta si está habilitado
        if ($this->preferMeta && $this->isMetaConfigured()) {
            Log::info('WhatsApp: Intentando envío vía Meta', ['to' => $to]);
            
            try {
                $result = $this->metaClient->sendText($to, $text);
                
                if ($result['ok']) {
                    Log::info('WhatsApp: Enviado exitosamente vía Meta', [
                        'to' => $to,
                        'message_id' => $result['message_id'] ?? null
                    ]);
                    return array_merge($result, ['provider' => 'meta']);
                }
                
                // Si Meta falla, intentar con Clicklab
                Log::warning('WhatsApp: Meta falló, intentando con Clicklab', [
                    'to' => $to,
                    'meta_error' => $result['body']['error'] ?? 'Unknown error'
                ]);
            } catch (Exception $e) {
                Log::warning('WhatsApp: Excepción en Meta, intentando con Clicklab', [
                    'to' => $to,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Usar Clicklab como fallback o por defecto
        Log::info('WhatsApp: Enviando vía Clicklab', ['to' => $to]);
        
        try {
            $result = $this->clicklabClient->sendWhatsappText($to, $text);
            
            if ($result['ok']) {
                Log::info('WhatsApp: Enviado exitosamente vía Clicklab', ['to' => $to]);
            }
            
            return array_merge($result, ['provider' => 'clicklab']);
        } catch (Exception $e) {
            Log::error('WhatsApp: Error en Clicklab también', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            
            return [
                'ok' => false,
                'status' => 500,
                'body' => ['error' => $e->getMessage()],
                'provider' => 'clicklab'
            ];
        }
    }

    /**
     * Enviar plantilla de WhatsApp con fallback
     */
    public function sendTemplate(string $to, string $templateName, array $parameters = [], string $language = 'es'): array
    {
        // Intentar Meta si está habilitado
        if ($this->preferMeta && $this->isMetaConfigured()) {
            Log::info('WhatsApp Template: Intentando vía Meta', [
                'to' => $to,
                'template' => $templateName
            ]);
            
            try {
                $result = $this->metaClient->sendTemplate($to, $templateName, $parameters, $language);
                
                if ($result['ok']) {
                    Log::info('WhatsApp Template: Enviado exitosamente vía Meta', [
                        'to' => $to,
                        'template' => $templateName,
                        'message_id' => $result['message_id'] ?? null
                    ]);
                    return array_merge($result, ['provider' => 'meta']);
                }
                
                Log::warning('WhatsApp Template: Meta falló, intentando con Clicklab', [
                    'to' => $to,
                    'template' => $templateName
                ]);
            } catch (Exception $e) {
                Log::warning('WhatsApp Template: Excepción en Meta, intentando con Clicklab', [
                    'to' => $to,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Usar Clicklab como fallback
        Log::info('WhatsApp Template: Enviando vía Clicklab', [
            'to' => $to,
            'template' => $templateName
        ]);
        
        try {
            $result = $this->clicklabClient->sendWhatsappTemplate($to, $templateName, $parameters, $language);
            
            if ($result['ok']) {
                Log::info('WhatsApp Template: Enviado exitosamente vía Clicklab', [
                    'to' => $to,
                    'template' => $templateName
                ]);
            }
            
            return array_merge($result, ['provider' => 'clicklab']);
        } catch (Exception $e) {
            Log::error('WhatsApp Template: Error en ambos proveedores', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            
            return [
                'ok' => false,
                'status' => 500,
                'body' => ['error' => $e->getMessage()],
                'provider' => 'clicklab'
            ];
        }
    }

    /**
     * Verificar si Meta está correctamente configurado
     */
    protected function isMetaConfigured(): bool
    {
        $token = config('meta.whatsapp_access_token');
        $phoneId = config('meta.whatsapp_phone_number_id');
        
        return !empty($token) && !empty($phoneId) && strlen($token) > 50;
    }

    /**
     * Obtener el proveedor actual
     */
    public function getCurrentProvider(): string
    {
        if ($this->preferMeta && $this->isMetaConfigured()) {
            return 'meta (with clicklab fallback)';
        }
        return 'clicklab';
    }
}
