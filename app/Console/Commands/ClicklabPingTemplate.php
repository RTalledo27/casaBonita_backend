<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ClicklabClient;

class ClicklabPingTemplate extends Command
{
    protected $signature = 'clicklab:ping-template {to} {name} {--lang=es} {--ns=} {--p=*}';
    protected $description = 'Enviar mensaje de plantilla de WhatsApp vía Clicklab/Infobip';

    public function handle(ClicklabClient $client)
    {
        $to = $this->argument('to');
        $name = $this->argument('name');
        $lang = (string)$this->option('lang');
        $ns = $this->option('ns') ?: null;
        $placeholders = (array)$this->option('p');

        $res = $client->sendWhatsappTemplate($to, $name, $placeholders, $lang, $ns);

        $this->info('Status: ' . $res['status']);
        $this->line('Body: ' . json_encode($res['body']));

        return $res['ok'] ? 0 : 1;
    }
}

