<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Verificando campo status ===\n\n";

$result = DB::select('SHOW COLUMNS FROM contracts WHERE Field = "status"');
print_r($result);

echo "\n=== Valores de status existentes ===\n";
$statuses = DB::select('SELECT DISTINCT status FROM contracts LIMIT 10');
foreach($statuses as $status) {
    echo "- {$status->status}\n";
}