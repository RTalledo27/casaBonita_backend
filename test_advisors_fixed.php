<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Modules\HumanResources\Http\Controllers\EmployeeController;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Repositories\EmployeeRepository;

// Crear una instancia de la aplicación Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing Fixed Advisors Endpoint ===\n";

try {
    // Verificar empleados en la base de datos
    $employees = Employee::with('user')->get();
    echo "Total employees in database: " . $employees->count() . "\n";
    
    // Verificar empleados que son asesores usando el scope
    $advisors = Employee::advisors()->with('user')->get();
    echo "Total advisors using scope: " . $advisors->count() . "\n";
    
    if ($advisors->count() > 0) {
        echo "\nAdvisors found using scope:\n";
        foreach ($advisors as $advisor) {
            echo "- ID: {$advisor->employee_id}, Code: {$advisor->employee_code}, Type: {$advisor->employee_type}";
            if ($advisor->user) {
                echo ", User: {$advisor->user->name} ({$advisor->user->email})";
            }
            echo "\n";
        }
    }
    
    // Probar el repositorio directamente
    echo "\n=== Testing Repository Method ===\n";
    $employeeModel = new Employee();
    $repository = new EmployeeRepository($employeeModel);
    $advisorsFromRepo = $repository->getAdvisors();
    echo "Advisors from repository: " . $advisorsFromRepo->count() . "\n";
    
    // Simular la llamada al endpoint
    echo "\n=== Testing Controller Method ===\n";
    
    $controller = new EmployeeController($repository, app('Modules\\HumanResources\\Services\\CommissionService'), app('Modules\\HumanResources\\Services\\BonusService'));
    $request = new Request();
    
    // Llamar al método advisors
    $response = $controller->advisors();
    
    if ($response instanceof Illuminate\Http\JsonResponse) {
        $data = $response->getData(true);
        echo "Response status: " . $response->getStatusCode() . "\n";
        echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        echo "Message: " . $data['message'] . "\n";
        echo "Data count: " . count($data['data']) . "\n";
        
        if (count($data['data']) > 0) {
            echo "\nFirst advisor from endpoint:\n";
            $firstAdvisor = $data['data'][0];
            echo "- ID: " . $firstAdvisor['employee_id'] . "\n";
            echo "- Code: " . $firstAdvisor['employee_code'] . "\n";
            echo "- Type: " . $firstAdvisor['employee_type'] . "\n";
            if (isset($firstAdvisor['user'])) {
                echo "- User: " . $firstAdvisor['user']['name'] . "\n";
            }
        }
    } else {
        echo "Unexpected response type: " . get_class($response) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test completed ===\n";