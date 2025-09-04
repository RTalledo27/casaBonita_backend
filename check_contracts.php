<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\Models\Contract;

try {
    $totalContracts = Contract::count();
    echo "Total de contratos: $totalContracts\n";
    
    $contractsWithFinancing = Contract::where('financing_amount', '>', 0)->count();
    echo "Contratos con financiamiento (financing_amount > 0): $contractsWithFinancing\n";
    
    $activeContractsWithFinancing = Contract::where('status', 'vigente')
        ->where('financing_amount', '>', 0)
        ->count();
    echo "Contratos activos con financiamiento: $activeContractsWithFinancing\n";
    
    // Mostrar algunos ejemplos
    $examples = Contract::where('financing_amount', '>', 0)
        ->select('contract_id', 'contract_number', 'financing_amount', 'status')
        ->limit(5)
        ->get();
    
    echo "\nEjemplos de contratos con financiamiento:\n";
    foreach ($examples as $contract) {
        echo "ID: {$contract->contract_id}, Número: {$contract->contract_number}, Monto: {$contract->financing_amount}, Estado: {$contract->status}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}