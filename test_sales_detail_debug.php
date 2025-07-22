<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Modules\HumanResources\Http\Controllers\CommissionController;
use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Repositories\CommissionRepository;
use Modules\HumanResources\Repositories\EmployeeRepository;

// Crear una instancia de la aplicación Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing Sales Detail Endpoint Debug ===\n";

try {
    // Crear instancias de los repositorios y servicios
    $commissionRepo = app(CommissionRepository::class);
    $employeeRepo = app(EmployeeRepository::class);
    $commissionService = new CommissionService($commissionRepo, $employeeRepo);
    
    // Crear el controlador
    $controller = new CommissionController($commissionRepo, $commissionService);
    
    // Crear la request
    $request = new Request([
        'employee_id' => 1,
        'month' => 7,
        'year' => 2025
    ]);
    
    echo "Testing with employee_id=1, month=7, year=2025\n";
    
    // Llamar al método getSalesDetail
    $response = $controller->getSalesDetail($request);
    
    if ($response instanceof Illuminate\Http\JsonResponse) {
        $data = $response->getData(true);
        echo "Response status: " . $response->getStatusCode() . "\n";
        echo "Response data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Unexpected response type: " . get_class($response) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test completed ===\n";