<?php

use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Employee;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ANÁLISIS DE VENTAS DE LUIS TAVARA - OCTUBRE 2025 ===\n\n";

// Buscar a Luis Tavara
$advisor = Employee::where('name', 'LIKE', '%Luis%Tavara%')
    ->orWhere('name', 'LIKE', '%Tavara%Luis%')
    ->first();

if (!$advisor) {
    echo "Buscando por otras variantes del nombre...\n";
    $advisors = Employee::where('name', 'LIKE', '%Tavara%')->get();
    
    if ($advisors->isEmpty()) {
        echo "No se encontró ningún empleado con 'Tavara' en el nombre\n";
        exit;
    }
    
    echo "Empleados encontrados con 'Tavara':\n";
    foreach ($advisors as $adv) {
        echo "  ID: {$adv->employee_id} - Nombre: {$adv->name}\n";
    }
    
    $advisor = $advisors->first();
    echo "\nUsando: ID {$advisor->employee_id} - {$advisor->name}\n\n";
}

echo "Asesor: {$advisor->name} (ID: {$advisor->employee_id})\n\n";

// Obtener todos los contratos de octubre 2025
$contracts = Contract::where('advisor_id', $advisor->employee_id)
    ->whereMonth('sign_date', 10)
    ->whereYear('sign_date', 2025)
    ->orderBy('sign_date')
    ->get();

echo "═══════════════════════════════════════════════════════════\n";
echo "TOTAL DE CONTRATOS ENCONTRADOS: " . $contracts->count() . "\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Categorizar contratos
$financed = [];
$cash = [];
$vigente = [];
$other_status = [];

foreach ($contracts as $contract) {
    echo sprintf(
        "%2d. %s | Firma: %s | Estado: %s\n",
        count($financed) + count($cash) + 1,
        $contract->contract_number,
        $contract->sign_date->format('Y-m-d'),
        $contract->status
    );
    
    echo sprintf(
        "    Total: S/ %s | Financiado: S/ %s | Plazo: %d meses\n",
        number_format($contract->total_price, 2),
        number_format($contract->financing_amount ?? 0, 2),
        $contract->term_months ?? 0
    );
    
    // Categorizar
    if ($contract->status === 'vigente') {
        $vigente[] = $contract;
        
        if ($contract->financing_amount && $contract->financing_amount > 0) {
            $financed[] = $contract;
        } else {
            $cash[] = $contract;
        }
    } else {
        $other_status[] = $contract;
    }
    
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════\n";
echo "RESUMEN:\n";
echo "  Total contratos: " . $contracts->count() . "\n";
echo "  - Vigentes: " . count($vigente) . "\n";
echo "    - Financiados: " . count($financed) . "\n";
echo "    - Contado: " . count($cash) . "\n";
echo "  - Otros estados: " . count($other_status) . "\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "CRITERIO DEL SISTEMA PARA CONTAR VENTAS:\n";
echo "  - Mes: octubre (sign_date)\n";
echo "  - Año: 2025\n";
echo "  - Status: 'vigente'\n";
echo "  - Financing_amount > 0 (solo financiados)\n\n";

$countedBySytem = Contract::where('advisor_id', $advisor->employee_id)
    ->whereMonth('sign_date', 10)
    ->whereYear('sign_date', 2025)
    ->where('status', 'vigente')
    ->whereNotNull('financing_amount')
    ->where('financing_amount', '>', 0)
    ->count();

echo "VENTAS CONTADAS POR EL SISTEMA: $countedBySytem\n";
echo "VENTAS EN EL EXCEL: 14\n";
echo "DIFERENCIA: " . ($countedBySytem - 14) . "\n\n";

if ($countedBySytem != 14) {
    echo "⚠️  HAY UNA DISCREPANCIA\n";
    echo "Posibles causas:\n";
    echo "  1. Contratos duplicados\n";
    echo "  2. Contratos que no deberían contarse (cancelados, etc.)\n";
    echo "  3. Diferencia en la fecha de corte (sign_date vs contract_date)\n";
}
