<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MetaWhatsAppClient;

class MetaWhatsAppTemplateTest extends Command
{
    protected $signature = 'meta:whatsapp-template {phone} {template} {--params=}';
    protected $description = 'Enviar plantilla de WhatsApp usando Meta API';

    public function handle()
    {
        $phone = $this->argument('phone');
        $template = $this->argument('template');
        $paramsJson = $this->option('params');
        
        $params = [];
        if ($paramsJson) {
            $params = json_decode($paramsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('❌ Error al parsear parámetros JSON');
                return 1;
            }
        }

        $this->info("📱 Enviando plantilla de WhatsApp a: {$phone}");
        $this->info("📋 Plantilla: {$template}");
        if (!empty($params)) {
            $this->info("📝 Parámetros: " . json_encode($params));
        }
        $this->newLine();

        try {
            $meta = app(MetaWhatsAppClient::class);
            $result = $meta->sendTemplate($phone, $template, $params, 'es');

            if ($result['ok']) {
                $this->info("✅ Plantilla enviada exitosamente!");
                if (isset($result['message_id'])) {
                    $this->info("🆔 Message ID: {$result['message_id']}");
                }
                $this->newLine();
                $this->line("Respuesta completa:");
                $this->line(json_encode($result['body'], JSON_PRETTY_PRINT));
            } else {
                $this->error("❌ Error al enviar plantilla");
                $this->line("Status: {$result['status']}");
                $this->line(json_encode($result['body'], JSON_PRETTY_PRINT));
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Excepción: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
