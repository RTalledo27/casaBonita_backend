<?php

require_once 'vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Sales\Models\Contract;
use Carbon\Carbon;

echo "Verificando datos de contratos...\n\n";

// Verificar total de contratos
$totalContracts = Contract::count();
echo "Total de contratos en la base de datos: {$totalContracts}\n\n";

if ($totalContracts > 0) {
    // Mostrar algunos contratos recientes
    echo "Últimos 5 contratos:\n";
    $recentContracts = Contract::orderBy('sign_date', 'desc')
        ->take(5)
        ->get(['contract_id', 'contract_number', 'sign_date']);
    
    foreach ($recentContracts as $contract) {
        echo "- ID: {$contract->contract_id}, Número: {$contract->contract_number}, Fecha firma: {$contract->sign_date}\n";
    }
    
    echo "\n";
    
    // Verificar contratos por año
    echo "Contratos por año:\n";
    $contractsByYear = Contract::selectRaw('YEAR(sign_date) as year, COUNT(*) as count')
        ->whereNotNull('sign_date')
        ->groupBy('year')
        ->orderBy('year', 'desc')
        ->get();
    
    foreach ($contractsByYear as $yearData) {
        echo "- Año {$yearData->year}: {$yearData->count} contratos\n";
    }
    
    echo "\n";
    
    // Verificar contratos de 2024 (año más probable)
    echo "Contratos de 2024 por mes:\n";
    $contracts2024 = Contract::selectRaw('MONTH(sign_date) as month, COUNT(*) as count')
        ->whereYear('sign_date', 2024)
        ->groupBy('month')
        ->orderBy('month')
        ->get();
    
    foreach ($contracts2024 as $monthData) {
        $monthName = Carbon::create(2024, $monthData->month, 1)->format('F');
        echo "- {$monthName}: {$monthData->count} contratos\n";
    }
    
    echo "\n";
    
    // Verificar contratos de 2025
    echo "Contratos de 2025 por mes:\n";
    $contracts2025 = Contract::selectRaw('MONTH(sign_date) as month, COUNT(*) as count')
        ->whereYear('sign_date', 2025)
        ->groupBy('month')
        ->orderBy('month')
        ->get();
    
    if ($contracts2025->count() > 0) {
        foreach ($contracts2025 as $monthData) {
            $monthName = Carbon::create(2025, $monthData->month, 1)->format('F');
            echo "- {$monthName}: {$monthData->count} contratos\n";
        }
    } else {
        echo "- No hay contratos en 2025\n";
    }
} else {
    echo "No hay contratos en la base de datos.\n";
}

echo "\n=== Verificación completada ===\n";