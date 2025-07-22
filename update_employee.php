<?php

use Illuminate\Foundation\Application;

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\HumanResources\Models\Employee;

echo "=== ACTUALIZANDO EMPLEADO ===\n";

$employee = Employee::find(1);
if ($employee) {
    $employee->name = 'Juan Pérez';
    $employee->position = 'Asesor de Ventas';
    $employee->email = 'juan.perez@casabonita.com';
    $employee->phone = '987654321';
    $employee->hire_date = '2024-01-01';
    $employee->base_salary = 2500.00;
    $employee->commission_rate = 0.05;
    $employee->save();
    
    echo "Empleado actualizado: {$employee->name} - {$employee->position}\n";
} else {
    echo "Empleado no encontrado\n";
}

echo "\n=== EMPLEADO ACTUALIZADO ===\n";