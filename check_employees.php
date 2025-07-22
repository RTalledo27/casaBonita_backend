<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Employee;

echo "=== VERIFICANDO EMPLEADOS EN LA BASE DE DATOS ===\n";

try {
    $employees = Employee::select('employee_id', 'employee_code', 'employee_type', 'user_id')
        ->with('user:user_id,first_name,last_name,email')
        ->get();
    
    echo "Total de empleados encontrados: " . $employees->count() . "\n\n";
    
    if ($employees->count() > 0) {
        foreach ($employees as $employee) {
            echo "ID: {$employee->employee_id}\n";
            echo "Código: {$employee->employee_code}\n";
            echo "Tipo: {$employee->employee_type}\n";
            if ($employee->user) {
                echo "Usuario: {$employee->user->first_name} {$employee->user->last_name} ({$employee->user->email})\n";
            }
            echo "---\n";
        }
    } else {
        echo "❌ No se encontraron empleados en la base de datos\n";
        echo "Esto explica por qué el endpoint sales-detail devuelve 'Empleado no encontrado'\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error al consultar empleados: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE VERIFICACIÓN ===\n";