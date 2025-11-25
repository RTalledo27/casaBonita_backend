<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$advisorId = 'EMP4357'; // Need to find integer ID first

$emp = DB::table('employees')->where('employee_id', $advisorId)->first();
if (!$emp) {
    // Maybe it's the integer ID?
    $emp = DB::table('employees')->where('id', $advisorId)->first(); // Assuming 'id' column exists? No, it's employee_id
    // Let's search by name or assume advisor_id in contracts is the string 'EMP4357'? 
    // No, advisor_id in contracts is integer usually.
    // Let's find the integer ID from the contract 412
    $c = DB::table('contracts')->where('contract_number', '202510-000000412')->first();
    $realAdvisorId = $c->advisor_id;
    echo "Advisor Integer ID: $realAdvisorId\n";
} else {
    $realAdvisorId = $emp->employee_id;
    echo "Advisor ID: $realAdvisorId\n";
}

echo "ANÁLISIS DE VENTAS PARA ASESOR $realAdvisorId (OCT 2025)\n";
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
