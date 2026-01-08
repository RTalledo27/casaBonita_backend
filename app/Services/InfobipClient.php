<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class InfobipClient
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected string $smsEndpoint;
    protected string $whatsappEndpoint;
    protected string $emailEndpoint;
    protected ?string $smsSender;
    protected ?string $whatsappSender;
    protected ?string $emailSender;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('infobip.base_url'), '/');
        $this->apiKey = config('infobip.api_key');
        $this->smsEndpoint = config('infobip.sms_endpoint');
        $this->whatsappEndpoint = config('infobip.whatsapp_endpoint');
        $this->emailEndpoint = config('infobip.email_endpoint');
        $this->smsSender = config('infobip.sms_sender');
        $this->whatsappSender = config('infobip.whatsapp_sender');
        $this->emailSender = config('infobip.email_sender');
    }

    protected function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'App ' . $this->apiKey,
        ];
    }

    public function sendSms(string $to, string $text): array
    {
        $payload = [
            'messages' => [[
                'from' => $this->smsSender,
                'destinations' => [[ 'to' => $this->normalizePhone($to) ]],
                'text' => $text,
            ]]
        ];

        $url = $this->baseUrl . $this->smsEndpoint;
        Log::info('Infobip SMS Request', [ 'url' => $url, 'to' => $to ]);
        
        $res = Http::withHeaders($this->headers())->post($url, $payload);
        
        Log::info('Infobip SMS Response', [ 'status' => $res->status(), 'body' => $res->json() ]);
        
        return ['ok' => $res->successful(), 'status' => $res->status(), 'body' => $res->json()];
    }

    public function sendWhatsappText(string $to, string $text): array
    {
        $payload = [
            'from' => $this->whatsappSender,
            'to' => $this->normalizePhone($to),
            'content' => ['text' => $text],
        ];

        $url = $this->baseUrl . $this->whatsappEndpoint;
        Log::info('Infobip WhatsApp Request', [ 'url' => $url, 'to' => $to ]);
        
        $res = Http::withHeaders($this->headers())->post($url, $payload);
        
        Log::info('Infobip WhatsApp Response', [ 'status' => $res->status(), 'body' => $res->json() ]);
        
        return ['ok' => $res->successful(), 'status' => $res->status(), 'body' => $res->json()];
    }

    public function sendWhatsappTemplate(string $to, string $templateName, array $placeholders = [], string $language = 'es', ?string $namespace = null): array
    {
        $payload = [
            'from' => $this->whatsappSender,
            'to' => $this->normalizePhone($to),
            'templateName' => $templateName,
            'language' => $language,
            'templateData' => [
                'body' => array_values($placeholders),
            ],
        ];
        
        if ($namespace) {
            $payload['templateNamespace'] = $namespace;
        }

        $url = $this->baseUrl . '/whatsapp/1/message/template';
        Log::info('Infobip WhatsApp Template Request', [ 'url' => $url, 'to' => $to, 'template' => $templateName ]);
        
        $res = Http::withHeaders($this->headers())->post($url, $payload);
        
        Log::info('Infobip WhatsApp Template Response', [ 'status' => $res->status(), 'body' => $res->json() ]);
        
        return ['ok' => $res->successful(), 'status' => $res->status(), 'body' => $res->json()];
    }

    public function sendEmail(string $to, string $subject, string $html): array
    {
        $fromEmail = $this->emailSender ?: config('mail.from.address');
        $fromName = config('infobip.email_sender_name') ?: config('mail.from.name');
        $sender = $fromName ? ($fromName . ' <' . $fromEmail . '>') : $fromEmail;
        $text = trim(strip_tags($html));
        
        $payload = [
            'messages' => [[
                'sender' => $sender,
                'destinations' => [
                    [
                        'to' => [[ 'destination' => $to ]],
                    ],
                ],
                'replyTo' => $fromEmail,
                'preserveRecipients' => false,
                'content' => [
                    'subject' => $subject,
                    'text' => $text,
                    'html' => $html,
                ],
            ]],
        ];

        $url = $this->baseUrl . $this->emailEndpoint;
        Log::info('Infobip Email Request', [ 'url' => $url, 'to' => $to, 'subject' => $subject ]);
        
        $res = Http::withHeaders($this->headers())->post($url, $payload);
        
        Log::info('Infobip Email Response', [ 'status' => $res->status(), 'body' => $res->json() ]);
        
        return ['ok' => $res->successful(), 'status' => $res->status(), 'body' => $res->json()];
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        
        // Si ya tiene código de país peruano
        if (Str::startsWith($digits, '51') && strlen($digits) >= 10) {
            return '+' . $digits;
        }
        
        // Si es un número peruano sin código
        if (Str::startsWith($digits, '9') && strlen($digits) === 9) {
            return '+51' . $digits;
        }
        
        // Remover ceros iniciales
        if (Str::startsWith($digits, '0')) {
            $digits = ltrim($digits, '0');
        }
        
        return '+' . $digits;
    }
}
