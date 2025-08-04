<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'bootstrap/app.php';

use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Repositories\CommissionRepository;
use Modules\HumanResources\Repositories\EmployeeRepository;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Models\Employee;

try {
    $month = isset($_GET['month']) ? (int)$_GET['month'] : 7;
    $year = isset($_GET['year']) ? (int)$_GET['year'] : 2025;
    
    echo "Testing API for month: $month, year: $year\n\n";
    
    // Crear servicios
    $employeeRepo = new EmployeeRepository(new Employee());
    $commissionRepo = new CommissionRepository(new Commission());
    $commissionService = new CommissionService($commissionRepo, $employeeRepo);
    
    // Obtener dashboard
    $dashboard = $commissionService->getAdminDashboard($month, $year);
    
    echo "Dashboard response:\n";
    echo json_encode($dashboard, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}