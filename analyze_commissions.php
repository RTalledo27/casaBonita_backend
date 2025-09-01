<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== REPORTE DE ANÁLISIS DE COMISIONES ===\n\n";

// 1. Total de comisiones
$totalCommissions = DB::table('commissions')->count();
echo "Total de comisiones: {$totalCommissions}\n\n";

// 2. Comisiones por empleado (top 15)
echo "=== COMISIONES POR EMPLEADO (TOP 15) ===\n";
$commissionsByEmployee = DB::table('commissions')
    ->select('employee_id', DB::raw('COUNT(*) as total'))
    ->groupBy('employee_id')
    ->orderBy('total', 'desc')
    ->limit(15)
    ->get();

foreach ($commissionsByEmployee as $item) {
    echo "Empleado {$item->employee_id}: {$item->total} comisiones\n";
}

// 3. Verificar duplicados por empleado y contrato
echo "\n=== COMISIONES DUPLICADAS POR EMPLEADO/CONTRATO ===\n";
$duplicates = DB::select("
    SELECT employee_id, contract_id, COUNT(*) as count 
    FROM commissions 
    GROUP BY employee_id, contract_id 
    HAVING COUNT(*) > 1 
    ORDER BY count DESC
    LIMIT 10
");

if (count($duplicates) > 0) {
    echo "PROBLEMA DETECTADO: Se encontraron comisiones duplicadas:\n";
    foreach ($duplicates as $duplicate) {
        echo "  - Empleado {$duplicate->employee_id}, Contrato {$duplicate->contract_id}: {$duplicate->count} comisiones\n";
    }
} else {
    echo "✓ No se encontraron comisiones duplicadas por empleado/contrato\n";
}

// 4. Verificar campo is_payable
echo "\n=== ESTADO DEL CAMPO IS_PAYABLE ===\n";
$payableStats = DB::table('commissions')
    ->select('is_payable', DB::raw('COUNT(*) as total'))
    ->groupBy('is_payable')
    ->get();

foreach ($payableStats as $stat) {
    $status = $stat->is_payable ? 'Pagables (is_payable=1)' : 'No pagables (is_payable=0)';
    echo "{$status}: {$stat->total} comisiones\n";
}

// 5. Análisis de comisiones padre vs hijas
echo "\n=== ANÁLISIS DE COMISIONES PADRE VS HIJAS ===\n";
$parentCommissions = DB::table('commissions')
    ->whereNull('parent_commission_id')
    ->count();

$childCommissions = DB::table('commissions')
    ->whereNotNull('parent_commission_id')
    ->count();

echo "Comisiones padre (parent_commission_id IS NULL): {$parentCommissions}\n";
echo "Comisiones hijas (parent_commission_id IS NOT NULL): {$childCommissions}\n";

// 6. Verificar tipos de comisión
echo "\n=== TIPOS DE COMISIÓN ===\n";
$commissionTypes = DB::table('commissions')
    ->select('commission_type', DB::raw('COUNT(*) as total'))
    ->groupBy('commission_type')
    ->orderBy('total', 'desc')
    ->get();

foreach ($commissionTypes as $type) {
    echo "Tipo '{$type->commission_type}': {$type->total} comisiones\n";
}

// 7. Total de contratos
echo "\n=== ESTADÍSTICAS GENERALES ===\n";
$totalContracts = DB::table('contracts')->count();
echo "Total de contratos: {$totalContracts}\n";

// 8. Empleados elegibles para comisiones
$eligibleEmployees = DB::table('employees')
    ->where('is_commission_eligible', true)
    ->count();

echo "Empleados elegibles para comisiones: {$eligibleEmployees}\n";

// 9. Empleados con comisiones vs elegibles
$employeesWithCommissions = DB::table('commissions')
    ->distinct('employee_id')
    ->count();

echo "Empleados que tienen comisiones: {$employeesWithCommissions}\n";

// 10. Análisis de problemas detectados
echo "\n=== RESUMEN DE PROBLEMAS DETECTADOS ===\n";

if (count($duplicates) > 0) {
    echo "❌ PROBLEMA 1: Comisiones duplicadas detectadas\n";
    echo "   - Se encontraron " . count($duplicates) . " casos de duplicación\n";
    echo "   - Esto indica que el sistema está creando múltiples comisiones para el mismo empleado/contrato\n\n";
}

if ($payableStats->where('is_payable', 0)->first()->total > 0) {
    $nonPayable = $payableStats->where('is_payable', 0)->first()->total;
    echo "❌ PROBLEMA 2: Comisiones no pagables\n";
    echo "   - {$nonPayable} comisiones tienen is_payable=0\n";
    echo "   - Estas comisiones no aparecerán en el frontend\n\n";
}

if ($employeesWithCommissions < $eligibleEmployees) {
    $missing = $eligibleEmployees - $employeesWithCommissions;
    echo "⚠️  PROBLEMA 3: Empleados elegibles sin comisiones\n";
    echo "   - {$missing} empleados elegibles no tienen comisiones asignadas\n\n";
}

echo "=== ANÁLISIS COMPLETADO ===\n";