<?php

use Modules\Sales\Models\Contract;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== BÚSQUEDA DE LUIS TAVARA ===\n\n";

// Buscar en employees
$employees = DB::table('employees')
    ->where('name', 'LIKE', '%Tavara%')
    ->orWhere('name', 'LIKE', '%TAVARA%')
    ->get();

if ($employees->isEmpty()) {
    echo "No se encontró ningún empleado con 'Tavara'\n";
    echo "Mostrando todos los asesores con contratos en octubre 2025:\n\n";
    
    $advisors = DB::table('contracts')
        ->select('advisor_id', DB::raw('count(*) as total'))
        ->whereMonth('sign_date', 10)
        ->whereYear('sign_date', 2025)
        ->whereNotNull('advisor_id')
        ->groupBy('advisor_id')
        ->orderBy('total', 'desc')
        ->get();
    
    foreach ($advisors as $adv) {
        $emp = DB::table('employees')->where('employee_id', $adv->advisor_id)->first();
        if ($emp) {
            echo "  ID: {$adv->advisor_id} - {$emp->name} ({$adv->total} contratos)\n";
        }
    }
    exit;
}

echo "Empleados encontrados:\n";
foreach ($employees as $emp) {
    echo "  ID: {$emp->employee_id} - {$emp->name}\n";
}

$advisorId = $employees->first()->employee_id;
$advisorName = $employees->first()->name;

echo "\n═══════════════════════════════════════════════════════════\n";
echo "Analizando: $advisorName (ID: $advisorId)\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Obtener todos los contratos
$contracts = DB::table('contracts')
    ->where('advisor_id', $advisorId)
    ->whereMonth('sign_date', 10)
    ->whereYear('sign_date', 2025)
    ->orderBy('sign_date')
    ->orderBy('contract_number')
    ->get();

echo "TOTAL CONTRATOS: " . $contracts->count() . "\n\n";

$n = 1;
$vigente_financed = 0;
$vigente_cash = 0;
$non_vigente = 0;

foreach ($contracts as $contract) {
    $isFinanced = $contract->financing_amount && $contract->financing_amount > 0;
    $statusMark = $contract->status === 'vigente' ? '✓' : '✗';
    $typeMark = $isFinanced ? 'F' : 'C';
    
    echo sprintf(
        "%2d. [%s][%s] %s | %s | S/ %s | S/ %s financ. | %d m\n",
        $n++,
        $statusMark,
        $typeMark,
        $contract->contract_number,
        $contract->sign_date,
        number_format($contract->total_price, 2),
        number_format($contract->financing_amount ?? 0, 2),
        $contract->term_months ?? 0
    );
    
    if ($contract->status === 'vigente') {
        if ($isFinanced) {
            $vigente_financed++;
        } else {
            $vigente_cash++;
        }
    } else {
        $non_vigente++;
        echo "      ⚠️  Status: {$contract->status}\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "RESUMEN:\n";
echo "  ✓ = Vigente | ✗ = Otro estado\n";
echo "  F = Financiado | C = Contado\n";
echo "\n";
echo "  Vigentes financiados: $vigente_financed\n";
echo "  Vigentes contado: $vigente_cash\n";
echo "  Otros estados: $non_vigente\n";
echo "  TOTAL: " . $contracts->count() . "\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "VENTAS CONTADAS POR SISTEMA (vigente + financing_amount > 0): $vigente_financed\n";
echo "VENTAS EN EXCEL DE ADMINISTRACIÓN: 14\n";
echo "DIFERENCIA: " . ($vigente_financed - 14) . "\n";
