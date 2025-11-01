<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing SalesReportsService...\n";
    
    // Create service instance
    $salesRepository = new \Modules\Reports\Repositories\SalesRepository();
    $salesService = new \Modules\Reports\Services\SalesReportsService($salesRepository);
    
    echo "Service created successfully\n";
    
    // Test getDashboardData method
    echo "Testing getDashboardData...\n";
    $data = $salesService->getDashboardData();
    
    echo "Dashboard data retrieved successfully\n";
    echo "Data structure:\n";
    print_r(array_keys($data));
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}