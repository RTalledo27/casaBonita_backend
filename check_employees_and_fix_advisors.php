<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Models\Employee;
use Modules\Sales\Models\Contract;

echo "=== VERIFICANDO EMPLEADOS Y ASIGNANDO ASESORES ===\n\n";

// 1. Verificar empleados existentes
echo "1. EMPLEADOS DISPONIBLES:\n";
$employees = Employee::all();
echo "Total empleados: " . $employees->count() . "\n\n";

if ($employees->count() > 0) {
    echo "Lista de empleados:\n";
    foreach ($employees as $employee) {
        echo "ID: {$employee->employee_id} - {$employee->full_name} - {$employee->position}\n";
    }
    echo "\n";
    
    // 2. Buscar empleados que sean asesores
    $advisors = Employee::where('position', 'LIKE', '%asesor%')
        ->orWhere('position', 'LIKE', '%vendedor%')
        ->orWhere('position', 'LIKE', '%ventas%')
        ->get();
    
    echo "2. ASESORES IDENTIFICADOS:\n";
    echo "Asesores encontrados: " . $advisors->count() . "\n";
    
    if ($advisors->count() > 0) {
        foreach ($advisors as $advisor) {
            echo "ID: {$advisor->employee_id} - {$advisor->full_name} - {$advisor->position}\n";
        }
        
        // 3. Asignar asesores a contratos sin advisor_id
        echo "\n3. ASIGNANDO ASESORES A CONTRATOS:\n";
        $contractsWithoutAdvisor = Contract::whereNull('advisor_id')
            ->where('status', 'vigente')
            ->get();
        
        echo "Contratos sin asesor: " . $contractsWithoutAdvisor->count() . "\n";
        
        if ($contractsWithoutAdvisor->count() > 0 && $advisors->count() > 0) {
            $advisorIndex = 0;
            $updated = 0;
            
            foreach ($contractsWithoutAdvisor as $contract) {
                // Asignar asesor de forma rotativa
                $advisor = $advisors[$advisorIndex % $advisors->count()];
                
                $contract->advisor_id = $advisor->employee_id;
                $contract->save();
                
                echo "Contract {$contract->contract_id} asignado a {$advisor->full_name}\n";
                
                $advisorIndex++;
                $updated++;
            }
            
            echo "\nContratos actualizados: {$updated}\n";
        }
    } else {
        echo "No se encontraron asesores. Creando asesor de ejemplo...\n";
        
        // Crear un asesor de ejemplo
        $advisor = Employee::create([
            'full_name' => 'Asesor de Ventas',
            'position' => 'Asesor de Ventas',
            'email' => 'asesor@casabonita.com',
            'phone' => '999999999',
            'hire_date' => now(),
            'status' => 'activo',
            'salary' => 1500.00
        ]);
        
        echo "Asesor creado: ID {$advisor->employee_id} - {$advisor->full_name}\n";
        
        // Asignar a todos los contratos
        $updated = Contract::whereNull('advisor_id')
            ->where('status', 'vigente')
            ->update(['advisor_id' => $advisor->employee_id]);
        
        echo "Contratos actualizados: {$updated}\n";
    }
} else {
    echo "No hay empleados en la base de datos. Creando empleados de ejemplo...\n";
    
    // Crear empleados de ejemplo
    $advisors = [
        ['name' => 'Juan Pérez', 'position' => 'Asesor de Ventas Senior'],
        ['name' => 'María García', 'position' => 'Asesor de Ventas'],
        ['name' => 'Carlos López', 'position' => 'Asesor de Ventas']
    ];
    
    $createdAdvisors = [];
    
    foreach ($advisors as $advisorData) {
        $advisor = Employee::create([
            'full_name' => $advisorData['name'],
            'position' => $advisorData['position'],
            'email' => strtolower(str_replace(' ', '.', $advisorData['name'])) . '@casabonita.com',
            'phone' => '999' . rand(100000, 999999),
            'hire_date' => now(),
            'status' => 'activo',
            'salary' => 1500.00
        ]);
        
        $createdAdvisors[] = $advisor;
        echo "Asesor creado: ID {$advisor->employee_id} - {$advisor->full_name}\n";
    }
    
    // Asignar asesores a contratos
    echo "\nAsignando asesores a contratos...\n";
    $contractsWithoutAdvisor = Contract::whereNull('advisor_id')
        ->where('status', 'vigente')
        ->get();
    
    $advisorIndex = 0;
    $updated = 0;
    
    foreach ($contractsWithoutAdvisor as $contract) {
        $advisor = $createdAdvisors[$advisorIndex % count($createdAdvisors)];
        
        $contract->advisor_id = $advisor->employee_id;
        $contract->save();
        
        echo "Contract {$contract->contract_id} asignado a {$advisor->full_name}\n";
        
        $advisorIndex++;
        $updated++;
    }
    
    echo "\nContratos actualizados: {$updated}\n";
}

echo "\n=== VERIFICACIÓN FINAL ===\n";
$contractsWithAdvisor = Contract::whereNotNull('advisor_id')
    ->where('status', 'vigente')
    ->count();
    
echo "Contratos con asesor asignado: {$contractsWithAdvisor}\n";

echo "\n=== PROCESO COMPLETADO ===\n";