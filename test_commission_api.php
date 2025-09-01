<?php

require_once 'vendor/autoload.php';

// Configurar la aplicación Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Repositories\CommissionRepository;
use Modules\HumanResources\Models\Commission;

echo "=== PRUEBA DE API DE COMISIONES ===\n\n";

// Instanciar el repositorio usando el contenedor de Laravel
$commissionRepo = app(CommissionRepository::class);

echo "1. Verificando comisiones con is_payable = true:\n";
$payableCommissions = Commission::where('is_payable', true)->get();
echo "Total comisiones con is_payable = true: " . $payableCommissions->count() . "\n\n";

if ($payableCommissions->count() > 0) {
    echo "Primeras 5 comisiones con is_payable = true:\n";
    foreach ($payableCommissions->take(5) as $commission) {
        echo "- ID: {$commission->commission_id}, Tipo: {$commission->commission_type}, Monto: {$commission->commission_amount}, Pagable: " . ($commission->is_payable ? 'Sí' : 'No') . "\n";
    }
    echo "\n";
}

echo "2. Verificando comisiones con is_payable = false:\n";
$nonPayableCommissions = Commission::where('is_payable', false)->get();
echo "Total comisiones con is_payable = false: " . $nonPayableCommissions->count() . "\n\n";

if ($nonPayableCommissions->count() > 0) {
    echo "Primeras 5 comisiones con is_payable = false:\n";
    foreach ($nonPayableCommissions->take(5) as $commission) {
        echo "- ID: {$commission->commission_id}, Tipo: {$commission->commission_type}, Monto: {$commission->commission_amount}, Pagable: " . ($commission->is_payable ? 'Sí' : 'No') . "\n";
    }
    echo "\n";
}

echo "3. Simulando llamada a la API getAll (sin filtros):\n";
try {
    $allCommissions = $commissionRepo->getAll([]);
    echo "Total comisiones devueltas por getAll(): " . $allCommissions->count() . "\n";
    
    $payableInResult = $allCommissions->where('is_payable', true)->count();
    $nonPayableInResult = $allCommissions->where('is_payable', false)->count();
    
    echo "- Comisiones pagables en resultado: {$payableInResult}\n";
    echo "- Comisiones no pagables en resultado: {$nonPayableInResult}\n\n";
    
    if ($payableInResult > 0) {
        echo "Estructura de una comisión pagable:\n";
        $samplePayable = $allCommissions->where('is_payable', true)->first();
        echo "ID: {$samplePayable->commission_id}\n";
        echo "Tipo: {$samplePayable->commission_type}\n";
        echo "Monto: {$samplePayable->commission_amount}\n";
        echo "Es pagable: " . ($samplePayable->is_payable ? 'true' : 'false') . "\n";
        echo "Estado de pago: {$samplePayable->payment_status}\n";
        echo "Empleado ID: {$samplePayable->employee_id}\n\n";
    }
    
} catch (Exception $e) {
    echo "Error al obtener comisiones: " . $e->getMessage() . "\n\n";
}

echo "4. Simulando llamada a la API con include_split_payments = true:\n";
try {
    $commissionsWithSplit = $commissionRepo->getAll([
        'include_split_payments' => true
    ]);
    echo "Total comisiones con include_split_payments: " . $commissionsWithSplit->count() . "\n";
    
    $payableInSplit = $commissionsWithSplit->where('is_payable', true)->count();
    $nonPayableInSplit = $commissionsWithSplit->where('is_payable', false)->count();
    
    echo "- Comisiones pagables: {$payableInSplit}\n";
    echo "- Comisiones no pagables: {$nonPayableInSplit}\n\n";
    
} catch (Exception $e) {
    echo "Error al obtener comisiones con split: " . $e->getMessage() . "\n\n";
}

echo "5. Verificando si hay filtros que excluyan comisiones pagables:\n";
echo "Revisando el método getAll en CommissionRepository...\n";

// Verificar si hay algún filtro activo que pueda estar excluyendo las comisiones
echo "\n=== RESUMEN ===\n";
echo "- Total comisiones pagables en BD: {$payableCommissions->count()}\n";
echo "- Total comisiones no pagables en BD: {$nonPayableCommissions->count()}\n";
echo "- Las comisiones pagables deberían aparecer en el frontend si no hay filtros adicionales.\n";

echo "\n=== FIN DE PRUEBA ===\n";