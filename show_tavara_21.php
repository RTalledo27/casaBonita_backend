<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$contracts = DB::select("
    SELECT 
        contract_number,
        sign_date,
        total_price,
        term_months
    FROM contracts
    WHERE advisor_id = 13
    AND MONTH(sign_date) = 10
    AND YEAR(sign_date) = 2025
    AND status = 'vigente'
    AND financing_amount > 0
    ORDER BY contract_number
");

echo "LUIS TAVARA - 21 CONTRATOS (Sistema)\n";
echo "Octubre 2025 | Excel dice: 14\n";
echo str_repeat("=", 65) . "\n\n";

$n = 1;
foreach ($contracts as $c) {
    echo sprintf("%2d. %-30s %s  S/ %10.2f\n",
        $n++,
        $c->contract_number,
        $c->sign_date,
        $c->total_price
    );
}

echo "\n" . str_repeat("=", 65) . "\n";
echo "TOTAL: " . count($contracts) . " | Diferencia: 7 contratos\n\n";

echo "SOLO NÚMEROS (copiar para comparar con Excel):\n";
echo str_repeat("-", 65) . "\n";
foreach ($contracts as $c) {
    echo $c->contract_number . "\n";
}
