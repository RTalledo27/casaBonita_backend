<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Sales\Models\Contract;
use Illuminate\Support\Facades\Schema;

echo "=== Verificando estructura de contratos ===\n\n";

// Verificar columnas de la tabla
$columns = Schema::getColumnListing('contracts');
echo "📋 Columnas disponibles en la tabla 'contracts':\n";
foreach ($columns as $column) {
    echo "   - {$column}\n";
}

echo "\n=== Listando contratos (solo columnas existentes) ===\n\n";

$contracts = Contract::orderBy('created_at', 'desc')
    ->take(10)
    ->get(['contract_number', 'sign_date', 'created_at']);

if ($contracts->count() > 0) {
    foreach ($contracts as $contract) {
        echo "📋 {$contract->contract_number}";
        echo " - Venta: " . ($contract->sign_date ? $contract->sign_date->format('Y-m-d') : 'NULL');
        echo " - Creado: " . ($contract->created_at ? $contract->created_at->format('Y-m-d') : 'NULL');
        echo "\n";
    }
} else {
    echo "❌ No se encontraron contratos\n";
}

echo "\n=== Buscando contratos con fechas de junio 2024 ===\n\n";

$juneContracts = Contract::whereYear('sign_date', 2024)
    ->whereMonth('sign_date', 6)
    ->get(['contract_number', 'sign_date']);

if ($juneContracts->count() > 0) {
    foreach ($juneContracts as $contract) {
        echo "🗓️ {$contract->contract_number}";
        echo " - Venta: " . ($contract->sign_date ? $contract->sign_date->format('Y-m-d') : 'NULL');
        echo "\n";
    }
} else {
    echo "❌ No se encontraron contratos de junio 2024\n";
}