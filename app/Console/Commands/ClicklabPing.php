<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ClicklabClient;

class ClicklabPing extends Command
{
    protected $signature = 'clicklab:ping {to} {channel=sms} {--text=Prueba Clicklab}';
    protected $description = 'Enviar mensaje de prueba vía Clicklab (sms|whatsapp|email)';

    public function handle(ClicklabClient $client)
    {
        $to = $this->argument('to');
        $channel = $this->argument('channel');
        $text = $this->option('text');

        switch ($channel) {
            case 'whatsapp':
                $res = $client->sendWhatsappText($to, $text);
                break;
            case 'email':
                $res = $client->sendEmail($to, 'Prueba Clicklab', '<b>' . e($text) . '</b>');
                break;
            default:
                $res = $client->sendSms($to, $text);
        }

        $this->info('Status: ' . $res['status']);
        $this->line('Body: ' . json_encode($res['body']));

        return $res['ok'] ? 0 : 1;
    }
}
