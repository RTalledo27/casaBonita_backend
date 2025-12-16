<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ClicklabClient
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected string $provider;
    protected string $smsEndpoint;
    protected string $whatsappEndpoint;
    protected string $emailEndpoint;
    protected ?string $smsSender;
    protected ?string $whatsappSender;
    protected ?string $emailSender;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('clicklab.base_url'), '/');
        $this->apiKey = config('clicklab.api_key');
        $this->provider = strtolower(config('clicklab.provider', 'infobip'));
        $this->smsEndpoint = config('clicklab.sms_endpoint');
        $this->whatsappEndpoint = config('clicklab.whatsapp_endpoint');
        $this->emailEndpoint = config('clicklab.email_endpoint');
        $this->smsSender = config('clicklab.sms_sender');
        $this->whatsappSender = config('clicklab.whatsapp_sender');
        $this->emailSender = config('clicklab.email_sender');
    }

    protected function headers(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->isInfobip()) {
            $headers['Authorization'] = 'App ' . $this->apiKey;
        } else {
            $headers['X-API-KEY'] = $this->apiKey;
        }

        return $headers;
    }

    protected function isInfobip(): bool
    {
        return $this->provider === 'infobip' || $this->provider === 'clicklab';
    }

    public function sendSms(string $to, string $text): array
    {
        if ($this->isInfobip()) {
            $payload = [
                'messages' => [[
                    'from' => $this->smsSender,
                    'destinations' => [[ 'to' => $this->normalizePhone($to) ]],
                    'text' => $text,
                ]]
            ];
        } else {
            $payload = [
                'sender' => $this->smsSender,
                'to' => $this->normalizePhone($to),
                'text' => $text,
            ];
        }
        $url = $this->baseUrl . $this->smsEndpoint;
        Log::info('Clicklab SMS', [ 'provider' => $this->provider, 'url' => $url ]);
        $res = Http::withHeaders($this->headers())->post($url, $payload);
        return ['ok' => $res->successful(), 'status' => $res->status(), 'body' => $res->json()];
    }

    public function sendWhatsappText(string $to, string $text): array
    {
        if ($this->isInfobip()) {
            $payload = [
                'from' => $this->whatsappSender,
                'to' => $this->normalizePhone($to),
                'content' => ['text' => $text],
            ];
        } else {
            $payload = [
                'sender' => $this->whatsappSender,
                'to' => $this->normalizePhone($to),
                'text' => $text,
            ];
        }
        $url = $this->baseUrl . $this->whatsappEndpoint;
        Log::info('Clicklab WhatsApp', [ 'provider' => $this->provider, 'url' => $url ]);
        $res = Http::withHeaders($this->headers())->post($url, $payload);
        return ['ok' => $res->successful(), 'status' => $res->status(), 'body' => $res->json()];
    }

    public function sendWhatsappTemplate(string $to, string $templateName, array $placeholders = [], string $language = 'es', ?string $namespace = null): array
    {
        if ($this->isInfobip()) {
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
            Log::info('Clicklab WhatsApp Template', [ 'provider' => $this->provider, 'url' => $url, 'name' => $templateName ]);
            $res = Http::withHeaders($this->headers())->post($url, $payload);
            return ['ok' => $res->successful(), 'status' => $res->status(), 'body' => $res->json()];
        }

        $url = $this->baseUrl . $this->whatsappEndpoint;
        $payload = [
            'sender' => $this->whatsappSender,
            'to' => $this->normalizePhone($to),
            'text' => implode(' ', $placeholders),
        ];
        Log::info('Clicklab WhatsApp (generic) fallback', [ 'provider' => $this->provider, 'url' => $url ]);
        $res = Http::withHeaders($this->headers())->post($url, $payload);
        return ['ok' => $res->successful(), 'status' => $res->status(), 'body' => $res->json()];
    }

    public function sendEmail(string $to, string $subject, string $html): array
    {
        $fromEmail = $this->emailSender ?: config('mail.from.address');
        $fromName = config('clicklab.email_sender_name') ?: config('mail.from.name');
        $sender = $fromName ? ($fromName . ' <' . $fromEmail . '>') : $fromEmail;
        $text = trim(strip_tags($html));
        if ($this->isInfobip()) {
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
        } else {
            $payload = [
                'from' => $fromEmail,
                'to' => $to,
                'subject' => $subject,
                'html' => $html,
                'text' => $text,
            ];
        }
        $url = $this->baseUrl . $this->emailEndpoint;
        Log::info('Clicklab Email', [ 'provider' => $this->provider, 'url' => $url ]);
        $res = Http::withHeaders($this->headers())->post($url, $payload);
        Log::info('Clicklab Email response', [ 'status' => $res->status(), 'body' => $res->json() ]);
        return ['ok' => $res->successful(), 'status' => $res->status(), 'body' => $res->json()];
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (Str::startsWith($digits, '51') && strlen($digits) >= 10) {
            return '+' . $digits;
        }
        if (Str::startsWith($digits, '9') && strlen($digits) === 9) {
            return '+51' . $digits;
        }
        if (Str::startsWith($digits, '0')) {
            $digits = ltrim($digits, '0');
        }
        return '+' . $digits;
    }
}
