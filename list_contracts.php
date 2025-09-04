<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Sales\Models\Contract;

echo "=== Listando contratos disponibles ===\n\n";

$contracts = Contract::orderBy('created_at', 'desc')
    ->take(15)
    ->get(['contract_number', 'sign_date', 'contract_date', 'created_at']);

if ($contracts->count() > 0) {
    foreach ($contracts as $contract) {
        echo "📋 {$contract->contract_number}";
        echo " - Venta: " . ($contract->sign_date ? $contract->sign_date->format('Y-m-d') : 'NULL');
        echo " - Contrato: " . ($contract->contract_date ? $contract->contract_date->format('Y-m-d') : 'NULL');
        echo " - Creado: " . $contract->created_at->format('Y-m-d');
        echo "\n";
    }
} else {
    echo "❌ No se encontraron contratos\n";
}

echo "\n=== Buscando contratos con fechas de junio ===\n\n";

$juneContracts = Contract::whereMonth('sign_date', 6)
    ->orWhereMonth('contract_date', 6)
    ->get(['contract_number', 'sign_date', 'contract_date']);

if ($juneContracts->count() > 0) {
    foreach ($juneContracts as $contract) {
        echo "🗓️ {$contract->contract_number}";
        echo " - Venta: " . ($contract->sign_date ? $contract->sign_date->format('Y-m-d') : 'NULL');
        echo " - Contrato: " . ($contract->contract_date ? $contract->contract_date->format('Y-m-d') : 'NULL');
        echo "\n";
    }
} else {
    echo "❌ No se encontraron contratos de junio\n";
}