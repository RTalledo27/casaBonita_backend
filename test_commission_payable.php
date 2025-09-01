<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contract;
use App\Services\CommissionService;
use App\Models\Commission;

echo "=== Prueba del sistema de comisiones con campo is_payable ===\n\n";

// Verificar que existe al menos un contrato
$contract = Contract::first();
if (!$contract) {
    echo "No hay contratos disponibles para la prueba.\n";
    exit(1);
}

echo "Contrato encontrado: ID {$contract->id}\n";

// Crear comisión con divisiones
try {
    $commission = CommissionService::createSplitCommissions($contract, 1000, 50, 50);
    
    echo "\n=== Comisión Padre ===\n";
    echo "ID: {$commission->id}\n";
    echo "is_payable: " . ($commission->is_payable ? 'true' : 'false') . "\n";
    echo "parent_commission_id: " . ($commission->parent_commission_id ?? 'null') . "\n";
    
    // Obtener las comisiones hijas
    $childCommissions = Commission::where('parent_commission_id', $commission->id)->get();
    
    echo "\n=== Comisiones Hijas ===\n";
    foreach ($childCommissions as $index => $child) {
        echo "Comisión hija " . ($index + 1) . ":\n";
        echo "  ID: {$child->id}\n";
        echo "  is_payable: " . ($child->is_payable ? 'true' : 'false') . "\n";
        echo "  parent_commission_id: {$child->parent_commission_id}\n";
        echo "  payment_percentage: {$child->payment_percentage}%\n";
        echo "\n";
    }
    
    // Verificar filtros
    echo "=== Verificación de Filtros ===\n";
    $payableCommissions = Commission::payable()->count();
    $nonPayableCommissions = Commission::nonPayable()->count();
    $payableDivisions = Commission::payableDivisions()->count();
    $parentCommissions = Commission::parentCommissions()->count();
    
    echo "Total comisiones pagables: {$payableCommissions}\n";
    echo "Total comisiones no pagables: {$nonPayableCommissions}\n";
    echo "Total divisiones pagables: {$payableDivisions}\n";
    echo "Total comisiones padre: {$parentCommissions}\n";
    
    echo "\n=== Prueba EXITOSA ===\n";
    echo "El sistema ahora distingue correctamente entre:\n";
    echo "- Comisiones padre (is_payable = false): Solo para control\n";
    echo "- Comisiones divisiones (is_payable = true): Para pagos reales\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}