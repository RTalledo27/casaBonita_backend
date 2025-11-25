<?php

use Modules\Sales\Models\Contract;
use Modules\HumanResources\Services\CommissionService;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$contract = Contract::where('contract_number', '202510-000000412')->first();

// Resolve from container
$service = $app->make(CommissionService::class);
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('getAdvisorFinancedSalesCount');
$method->setAccessible(true);

$salesCount = $method->invoke($service, $contract->advisor_id, $contract->sign_date);

echo "Sales Count returned by service: $salesCount\n";
