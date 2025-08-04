<?php

require_once 'vendor/autoload.php';

// Configurar Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Models\Employee;
use Modules\Sales\Models\Contract;

echo "=== VERIFICANDO EMPLEADOS Y ASESORES ===\n";

// 1. Verificar empleados existentes
$employees = Employee::with('user')->get();
echo "Total empleados: " . $employees->count() . "\n\n";

foreach ($employees as $employee) {
    echo "ID: {$employee->employee_id} | Tipo: {$employee->employee_type} | Nombre: {$employee->full_name}\n";
}

echo "\n=== BUSCANDO ASESORES ===\n";

// 2. Buscar empleados que sean asesores
$advisors = Employee::whereIn('employee_type', ['asesor_inmobiliario', 'vendedor'])
    ->with('user')
    ->get();

echo "Total asesores encontrados: " . $advisors->count() . "\n\n";

foreach ($advisors as $advisor) {
    echo "Asesor ID: {$advisor->employee_id} | Tipo: {$advisor->employee_type} | Nombre: {$advisor->full_name}\n";
}

echo "\n=== CONTRATOS SIN ASESOR ===\n";

// 3. Verificar contratos sin advisor_id
$contractsWithoutAdvisor = Contract::whereNull('advisor_id')
    ->orWhere('advisor_id', '')
    ->get();

echo "Contratos sin asesor: " . $contractsWithoutAdvisor->count() . "\n\n";

if ($advisors->count() > 0 && $contractsWithoutAdvisor->count() > 0) {
    echo "=== ASIGNANDO ASESORES A CONTRATOS ===\n";
    
    // Tomar el primer asesor disponible
    $defaultAdvisor = $advisors->first();
    echo "Usando asesor por defecto: {$defaultAdvisor->full_name} (ID: {$defaultAdvisor->employee_id})\n\n";
    
    $updated = 0;
    foreach ($contractsWithoutAdvisor as $contract) {
        $contract->advisor_id = $defaultAdvisor->employee_id;
        $contract->save();
        $updated++;
        echo "Contrato {$contract->contract_id} actualizado con asesor {$defaultAdvisor->employee_id}\n";
    }
    
    echo "\nTotal contratos actualizados: {$updated}\n";
} else {
    if ($advisors->count() == 0) {
        echo "ERROR: No se encontraron asesores en el sistema\n";
    }
    if ($contractsWithoutAdvisor->count() == 0) {
        echo "INFO: Todos los contratos ya tienen asesor asignado\n";
    }
}

echo "\n=== PROCESO COMPLETADO ===\n";