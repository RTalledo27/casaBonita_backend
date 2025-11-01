<?php

require_once __DIR__ . '/vendor/autoload.php';

use Modules\Reports\Services\SalesReportsService;
use Modules\Reports\Services\PaymentSchedulesService;
use Modules\Reports\Services\ProjectionsService;
use Modules\Reports\Repositories\SalesRepository;
use Modules\Reports\Repositories\PaymentsRepository;
use Modules\Reports\Repositories\ProjectionsRepository;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing Sales Reports Service...\n";
    $salesRepository = new SalesRepository();
    $salesService = new SalesReportsService($salesRepository);
    $salesData = $salesService->getDashboardData();
    echo "Sales dashboard data retrieved successfully!\n";
    print_r($salesData);
    
    echo "\n\nTesting Payment Schedules Service...\n";
    $paymentsRepository = new PaymentsRepository();
    $paymentService = new PaymentSchedulesService($paymentsRepository);
    $paymentData = $paymentService->getOverview();
    echo "Payment schedules overview retrieved successfully!\n";
    print_r($paymentData);
    
    echo "\n\nTesting Projections Service...\n";
    $projectionsRepository = new ProjectionsRepository();
    $projectionsService = new ProjectionsService($projectionsRepository);
    $projectionsData = $projectionsService->getSalesProjections();
    echo "Sales projections retrieved successfully!\n";
    print_r($projectionsData);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}