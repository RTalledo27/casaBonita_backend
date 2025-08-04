<?php

require_once 'vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Sales\Models\Contract;

echo "Verificando status de contratos...\n\n";

// Verificar todos los contratos de enero 2025
$contracts = Contract::whereMonth('sign_date', 1)
    ->whereYear('sign_date', 2025)
    ->get(['contract_id', 'contract_number', 'status', 'advisor_id', 'financing_amount', 'term_months']);

echo "Contratos de enero 2025:\n";
foreach ($contracts as $contract) {
    echo "- ID: {$contract->contract_id}\n";
    echo "  Número: {$contract->contract_number}\n";
    echo "  Status: {$contract->status}\n";
    echo "  Advisor ID: {$contract->advisor_id}\n";
    echo "  Monto financiado: {$contract->financing_amount}\n";
    echo "  Plazo (meses): {$contract->term_months}\n";
    echo "\n";
}

// Verificar todos los status únicos en la base de datos
echo "\nTodos los status únicos en la base de datos:\n";
$uniqueStatuses = Contract::distinct()->pluck('status');
foreach ($uniqueStatuses as $status) {
    $count = Contract::where('status', $status)->count();
    echo "- '{$status}': {$count} contratos\n";
}

echo "\n=== Verificación completada ===\n";