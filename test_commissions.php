<?php

use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Verificar comisiones duplicadas
echo "=== VERIFICACIÓN DE COMISIONES DUPLICADAS ===\n";

// Consultar comisiones de diciembre 2024
$commissions = DB::table('commissions')
    ->select('employee_id', 'contract_id', 'period_month', 'period_year', 'is_payable', 'parent_commission_id', 'commission_amount')
    ->where('period_month', 06)
    ->where('period_year', 2025)
    ->orderBy('employee_id')
    ->orderBy('contract_id')
    ->get();

echo "Total comisiones encontradas para diciembre 2024: " . $commissions->count() . "\n\n";

// Agrupar por employee_id y contract_id para detectar duplicados
$grouped = $commissions->groupBy(function($item) {
    return $item->employee_id . '_' . $item->contract_id;
});

echo "=== ANÁLISIS DE DUPLICADOS ===\n";
$duplicates = 0;
$totalGroups = 0;

foreach ($grouped as $key => $group) {
    $totalGroups++;
    if ($group->count() > 3) { // Más de 3 (1 padre + 2 hijos) indica duplicados
        $duplicates++;
        echo "DUPLICADO DETECTADO - Employee/Contract: {$key} - Total registros: {$group->count()}\n";
        foreach ($group as $commission) {
            echo "  - Employee: {$commission->employee_id}, Contract: {$commission->contract_id}, Payable: {$commission->is_payable}, Parent: {$commission->parent_commission_id}\n";
        }
        echo "\n";
    }
}

echo "\n=== RESUMEN ===\n";
echo "Total grupos employee/contract: {$totalGroups}\n";
echo "Grupos con duplicados: {$duplicates}\n";
echo "Porcentaje de duplicados: " . ($totalGroups > 0 ? round(($duplicates / $totalGroups) * 100, 2) : 0) . "%\n";

// Verificar estructura correcta (1 padre + 2 hijos por grupo)
echo "\n=== VERIFICACIÓN DE ESTRUCTURA CORRECTA ===\n";
$correctStructure = 0;
foreach ($grouped as $key => $group) {
    $parents = $group->where('is_payable', 0)->count();
    $children = $group->where('is_payable', 1)->count();
    
    if ($parents == 1 && $children == 2) {
        $correctStructure++;
    } else {
        echo "ESTRUCTURA INCORRECTA - {$key}: {$parents} padres, {$children} hijos\n";
    }
}

echo "Grupos con estructura correcta: {$correctStructure}/{$totalGroups}\n";
echo "Porcentaje de estructura correcta: " . ($totalGroups > 0 ? round(($correctStructure / $totalGroups) * 100, 2) : 0) . "%\n";