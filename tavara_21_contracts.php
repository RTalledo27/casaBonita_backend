<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$advisorId = 13; // Luis Tavara

echo "LUIS TAVARA - CONTRATOS CONTADOS EN OCTUBRE 2025\n";
echo "Total: 21 contratos\n\n";
echo "FORMATO PARA COMPARAR CON EXCEL:\n";
echo str_repeat("=", 80) . "\n\n";

$contracts = DB::select("
    SELECT 
        contract_number,
        sign_date,
        total_price,
        financing_amount,
        term_months
    FROM contracts
    WHERE advisor_id = ?
    AND MONTH(sign_date) = 10
    AND YEAR(sign_date) = 2025
    AND status = 'vigente'
    AND financing_amount > 0
    ORDER BY sign_date, contract_number
", [$advisorId]);

echo "Nº  | CONTRATO                  | FECHA       | TOTAL         | PLAZO\n";
echo str_repeat("-", 80) . "\n";

$n = 1;
foreach ($contracts as $c) {
    echo sprintf(
        "%-3d | %-25s | %s | S/ %10.2f | %2d meses\n",
        $n++,
        $c->contract_number,
        $c->sign_date,
        $c->total_price,
        $c->term_months
    );
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "TOTAL: " . count($contracts) . " contratos\n";
echo "\nCONTRATOS (solo números para copiar):\n";
echo str_repeat("-", 80) . "\n";

foreach ($contracts as $c) {
    echo $c->contract_number . "\n";
}

echo "\n\nINSTRUCCIONES:\n";
echo "1. Copia la lista de números de contrato de arriba\n";
echo "2. Compara con los 14 del Excel\n";
echo "3. Identifica cuáles 7 contratos están de más en el sistema\n";
echo "4. Avísame cuáles son para investigar por qué se están contando\n";
