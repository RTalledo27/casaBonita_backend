<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Repositories\EmployeeRepository;
use Modules\HumanResources\Models\Employee;

echo "=== PROBANDO ENDPOINT ADVISORS ===\n";

try {
    // Probar directamente el scope advisors
    echo "\n1. Probando scope advisors() directamente:\n";
    $advisorsFromScope = Employee::with(['user', 'team'])->advisors()->get();
    echo "Cantidad de asesores encontrados: " . $advisorsFromScope->count() . "\n";
    
    foreach ($advisorsFromScope as $advisor) {
        echo "- ID: {$advisor->employee_id}, Código: {$advisor->employee_code}, Tipo: {$advisor->employee_type}\n";
        if ($advisor->user) {
            echo "  Usuario: {$advisor->user->first_name} {$advisor->user->last_name}\n";
        }
    }
    
    // Probar el repositorio
    echo "\n2. Probando EmployeeRepository->getAdvisors():\n";
    $employeeRepo = new EmployeeRepository(new Employee());
    $advisorsFromRepo = $employeeRepo->getAdvisors();
    echo "Cantidad de asesores desde repositorio: " . $advisorsFromRepo->count() . "\n";
    
    foreach ($advisorsFromRepo as $advisor) {
        echo "- ID: {$advisor->employee_id}, Código: {$advisor->employee_code}, Tipo: {$advisor->employee_type}\n";
        if ($advisor->user) {
            echo "  Usuario: {$advisor->user->first_name} {$advisor->user->last_name}\n";
        }
    }
    
    // Verificar tipos de empleados
    echo "\n3. Verificando todos los tipos de empleados:\n";
    $allEmployees = Employee::select('employee_type')->distinct()->get();
    foreach ($allEmployees as $emp) {
        echo "- Tipo: {$emp->employee_type}\n";
    }
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DE PRUEBA ===\n";