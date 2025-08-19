<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Employee;

echo "Buscando empleados con 'DANIELA' en el nombre...\n";

$employees = Employee::whereHas('user', function($q) {
    $q->where('first_name', 'LIKE', '%DANIELA%');
})->with('user')->get();

if ($employees->count() > 0) {
    echo "Empleados encontrados:\n";
    foreach ($employees as $employee) {
        echo "- {$employee->user->first_name} {$employee->user->last_name} (ID: {$employee->employee_id})\n";
    }
} else {
    echo "No se encontraron empleados con 'DANIELA' en el nombre.\n";
    echo "\nListando todos los empleados para referencia:\n";
    
    $allEmployees = Employee::with('user')->take(10)->get();
    foreach ($allEmployees as $employee) {
        $firstName = $employee->user->first_name ?? 'N/A';
        $lastName = $employee->user->last_name ?? 'N/A';
        echo "- {$firstName} {$lastName} (ID: {$employee->employee_id})\n";
    }
}