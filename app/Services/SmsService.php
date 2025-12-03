<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private function normalize(string $phone): string
    {
        $p = preg_replace('/\D+/', '', $phone ?? '');
        if (!$p) return $phone;
        // Perú (+51) por defecto si se detecta 9 dígitos locales
        if (strlen($p) === 9 && $p[0] !== '0') {
            return '+51' . $p;
        }
        // Si ya incluye código de país (e.g. 519XXXXXXXX), prepender '+'
        if ($p[0] !== '+') {
            return '+' . $p;
        }
        return $p;
    }

    public function send(string $phone, string $message): bool
    {
        // Preferir Infobip si está configurado
        $infobipKey = config('services.infobip.api_key');
        $infobipBase = rtrim(config('services.infobip.base_url') ?: 'https://api.infobip.com', '/');
        $infobipSender = config('services.infobip.sender');

        if ($infobipKey && $infobipBase) {
            try {
                $to = $this->normalize($phone);
                $resp = Http::withHeaders([
                    'Authorization' => 'App ' . $infobipKey,
                    'Content-Type' => 'application/json'
                ])->post($infobipBase . '/sms/2/text/advanced', [
                    'messages' => [[
                        // 'from' opcional; algunos destinos requieren remitente aprobado
                        ...( $infobipSender ? ['from' => $infobipSender] : [] ),
                        'destinations' => [[ 'to' => $to ]],
                        'text' => $message,
                    ]]
                ]);
                if ($resp->successful()) {
                    $json = $resp->json();
                    // Verificar estado por mensaje
                    if (isset($json['messages'][0]['status']['groupId'])) {
                        $gid = (int)$json['messages'][0]['status']['groupId'];
                        // 1 = PENDING/ACCEPTED según Infobip; considerar éxito
                        if ($gid === 1) return true;
                    }
                    // Si no hay estructura, considerar éxito por HTTP 200
                    return true;
                }
                Log::error('Infobip SMS failed', ['status' => $resp->status(), 'body' => $resp->body()]);
            } catch (\Throwable $e) {
                Log::error('Infobip SMS exception', ['error' => $e->getMessage()]);
            }
        }

        // Fallback genérico
        $url = config('services.sms.url');
        $apiKey = config('services.sms.key');
        $sender = config('services.sms.sender');

        if (!$url || !$apiKey) {
            Log::warning('SMS not configured, skipping send', ['phone' => $phone]);
            return false;
        }

        try {
            $resp = Http::withToken($apiKey)->post($url, [
                'to' => $phone,
                'from' => $sender,
                'message' => $message,
            ]);
            if ($resp->successful()) {
                return true;
            }
            Log::error('SMS gateway failed', ['status' => $resp->status(), 'body' => $resp->body()]);
            return false;
        } catch (\Throwable $e) {
            Log::error('SMS send exception', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
