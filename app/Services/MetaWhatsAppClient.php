<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppClient
{
    protected string $baseUrl;
    protected ?string $accessToken;
    protected ?string $phoneNumberId;
    protected ?string $businessAccountId;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('meta.whatsapp_base_url', 'https://graph.facebook.com'), '/');
        $this->accessToken = config('meta.whatsapp_access_token');
        $this->phoneNumberId = config('meta.whatsapp_phone_number_id');
        $this->businessAccountId = config('meta.whatsapp_business_account_id');
        
        // Validar configuración
        if (!$this->accessToken || !$this->phoneNumberId) {
            Log::error('Meta WhatsApp no configurado. Verifica las variables META_WHATSAPP_ACCESS_TOKEN y META_WHATSAPP_PHONE_NUMBER_ID en .env');
        }
    }

    protected function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->accessToken,
        ];
    }

    /**
     * Normalizar número telefónico al formato internacional
     */
    protected function normalizePhone(string $phone): string
    {
        $clean = preg_replace('/[^0-9+]/', '', $phone);
        
        // Si empieza con +51 (Perú), dejarlo así
        if (str_starts_with($clean, '+51')) {
            return $clean;
        }
        
        // Si empieza con 51, agregar +
        if (str_starts_with($clean, '51')) {
            return '+' . $clean;
        }
        
        // Si es un número peruano de 9 dígitos, agregar +51
        if (strlen($clean) === 9 && !str_starts_with($clean, '+')) {
            return '+51' . $clean;
        }
        
        // Si tiene un + al inicio, dejarlo
        if (str_starts_with($clean, '+')) {
            return $clean;
        }
        
        // Por defecto, asumir que es Perú
        return '+51' . $clean;
    }

    /**
     * Enviar mensaje de texto simple por WhatsApp
     * 
     * @param string $to Número de teléfono destino (formato internacional)
     * @param string $text Mensaje de texto
     * @return array ['ok' => bool, 'status' => int, 'body' => array]
     */
    public function sendText(string $to, string $text): array
    {
        if (!$this->accessToken || !$this->phoneNumberId) {
            return [
                'ok' => false,
                'status' => 500,
                'body' => ['error' => 'Meta WhatsApp no está configurado. Verifica las variables de entorno: META_WHATSAPP_ACCESS_TOKEN, META_WHATSAPP_PHONE_NUMBER_ID']
            ];
        }
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($to),
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $text
            ]
        ];

        $url = "{$this->baseUrl}/v18.0/{$this->phoneNumberId}/messages";
        
        Log::info('Meta WhatsApp Text Request', [
            'url' => $url,
            'to' => $this->normalizePhone($to),
            'text_length' => strlen($text)
        ]);
        
        try {
            $res = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post($url, $payload);
            
            $body = $res->json();
            
            Log::info('Meta WhatsApp Text Response', [
                'status' => $res->status(),
                'body' => $body
            ]);
            
            return [
                'ok' => $res->successful(),
                'status' => $res->status(),
                'body' => $body,
                'message_id' => $body['messages'][0]['id'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('Meta WhatsApp Text Error', [
                'error' => $e->getMessage(),
                'to' => $to
            ]);
            
            return [
                'ok' => false,
                'status' => 500,
                'body' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Enviar plantilla de WhatsApp
     * 
     * @param string $to Número de teléfono destino
     * @param string $templateName Nombre de la plantilla
     * @param array $parameters Parámetros de la plantilla [['type' => 'text', 'text' => 'valor']]
     * @param string $language Código de idioma (default: es)
     * @return array
     */
    public function sendTemplate(string $to, string $templateName, array $parameters = [], string $language = 'es'): array
    {
        if (!$this->accessToken || !$this->phoneNumberId) {
            return [
                'ok' => false,
                'status' => 500,
                'body' => ['error' => 'Meta WhatsApp no está configurado. Verifica las variables de entorno.']
            ];
        }
        
        // Convertir parámetros simples a formato Meta
        $formattedParams = [];
        foreach ($parameters as $param) {
            if (is_string($param)) {
                $formattedParams[] = [
                    'type' => 'text',
                    'text' => $param
                ];
            } elseif (is_array($param) && isset($param['type'])) {
                $formattedParams[] = $param;
            }
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($to),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $language
                ]
            ]
        ];

        // Solo agregar components si hay parámetros
        if (!empty($formattedParams)) {
            $payload['template']['components'] = [
                [
                    'type' => 'body',
                    'parameters' => $formattedParams
                ]
            ];
        }

        $url = "{$this->baseUrl}/v18.0/{$this->phoneNumberId}/messages";
        
        Log::info('Meta WhatsApp Template Request', [
            'url' => $url,
            'to' => $this->normalizePhone($to),
            'template' => $templateName,
            'parameters_count' => count($formattedParams)
        ]);
        
        try {
            $res = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post($url, $payload);
            
            $body = $res->json();
            
            Log::info('Meta WhatsApp Template Response', [
                'status' => $res->status(),
                'body' => $body
            ]);
            
            return [
                'ok' => $res->successful(),
                'status' => $res->status(),
                'body' => $body,
                'message_id' => $body['messages'][0]['id'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('Meta WhatsApp Template Error', [
                'error' => $e->getMessage(),
                'to' => $to,
                'template' => $templateName
            ]);
            
            return [
                'ok' => false,
                'status' => 500,
                'body' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Enviar imagen por WhatsApp
     */
    public function sendImage(string $to, string $imageUrl, ?string $caption = null): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($to),
            'type' => 'image',
            'image' => [
                'link' => $imageUrl
            ]
        ];

        if ($caption) {
            $payload['image']['caption'] = $caption;
        }

        $url = "{$this->baseUrl}/v18.0/{$this->phoneNumberId}/messages";
        
        Log::info('Meta WhatsApp Image Request', [
            'url' => $url,
            'to' => $this->normalizePhone($to)
        ]);
        
        try {
            $res = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post($url, $payload);
            
            return [
                'ok' => $res->successful(),
                'status' => $res->status(),
                'body' => $res->json()
            ];
        } catch (\Exception $e) {
            Log::error('Meta WhatsApp Image Error', ['error' => $e->getMessage()]);
            return [
                'ok' => false,
                'status' => 500,
                'body' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Enviar documento por WhatsApp
     */
    public function sendDocument(string $to, string $documentUrl, ?string $filename = null, ?string $caption = null): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($to),
            'type' => 'document',
            'document' => [
                'link' => $documentUrl
            ]
        ];

        if ($filename) {
            $payload['document']['filename'] = $filename;
        }

        if ($caption) {
            $payload['document']['caption'] = $caption;
        }

        $url = "{$this->baseUrl}/v18.0/{$this->phoneNumberId}/messages";
        
        try {
            $res = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post($url, $payload);
            
            return [
                'ok' => $res->successful(),
                'status' => $res->status(),
                'body' => $res->json()
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'status' => 500,
                'body' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Marcar mensaje como leído
     */
    public function markAsRead(string $messageId): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId
        ];

        $url = "{$this->baseUrl}/v18.0/{$this->phoneNumberId}/messages";
        
        try {
            $res = Http::withHeaders($this->headers())
                ->post($url, $payload);
            
            return [
                'ok' => $res->successful(),
                'status' => $res->status(),
                'body' => $res->json()
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'status' => 500,
                'body' => ['error' => $e->getMessage()]
            ];
        }
    }
}
