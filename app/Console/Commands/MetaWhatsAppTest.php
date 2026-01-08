<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MetaWhatsAppClient;

class MetaWhatsAppTest extends Command
{
    protected $signature = 'meta:whatsapp-test {phone} {message?}';
    protected $description = 'Enviar mensaje de prueba por WhatsApp usando Meta API (solo Meta)';

    public function handle()
    {
        $phone = $this->argument('phone');
        $message = $this->argument('message') ?: 'Hola! Este es un mensaje de prueba desde Meta WhatsApp API de Casa Bonita.';

        $this->info("📱 Enviando WhatsApp a: {$phone}");
        $this->info("💬 Mensaje: {$message}");
        $this->info("🔧 Proveedor: Meta WhatsApp Cloud API");
        $this->newLine();

        try {
            $meta = app(MetaWhatsAppClient::class);
            $result = $meta->sendText($phone, $message);

            if ($result['ok']) {
                $this->info("✅ Mensaje enviado exitosamente vía META!");
                if (isset($result['message_id'])) {
                    $this->info("🆔 Message ID: {$result['message_id']}");
                }
                $this->newLine();
                $this->line("Respuesta completa:");
                $this->line(json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->error("❌ Error al enviar mensaje vía Meta");
                $this->line("Status: {$result['status']}");
                $this->line(json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->newLine();
                $this->warn("🔧 Verifica tu configuración de Meta:");
                $this->line("   - META_WHATSAPP_ACCESS_TOKEN");
                $this->line("   - META_WHATSAPP_PHONE_NUMBER_ID");
                $this->newLine();
                $this->line("Obtén tus credenciales en:");
                $this->line("https://developers.facebook.com/apps/");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Excepción: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
