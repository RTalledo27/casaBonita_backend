<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get advisor ID from contract 412
$c = DB::table('contracts')->where('contract_number', '202510-000000412')->first();
if (!$c) {
    echo "Contrato 412 no encontrado\n";
    exit;
}
$realAdvisorId = $c->advisor_id;
echo "Advisor ID del contrato 412: $realAdvisorId\n\n";

echo "ANÁLISIS DE VENTAS (OCT 2025)\n";
echo str_repeat("=", 80) . "\n";

$contracts = DB::select("
    SELECT 
        contract_number,
        contract_date,
        status,
        financing_amount
    FROM contracts
    WHERE advisor_id = ?
    AND MONTH(contract_date) = 10
    AND YEAR(contract_date) = 2025
    ORDER BY contract_date
", [$realAdvisorId]);

$count = 0;
foreach ($contracts as $c) {
    $isCounted = ($c->status === 'vigente' && $c->financing_amount > 0);
    if ($isCounted) $count++;
    
    echo sprintf("%-25s %-12s %-10s S/ %-10.2f %s\n",
        $c->contract_number,
        $c->contract_date,
        $c->status,
        $c->financing_amount ?? 0,
        $isCounted ? "✓" : "✗"
    );
}

echo "\nTotal Contados: $count\n";
