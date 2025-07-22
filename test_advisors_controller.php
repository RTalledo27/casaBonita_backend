<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Http\Controllers\EmployeeController;
use Modules\HumanResources\Repositories\EmployeeRepository;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Services\BonusService;
use Modules\HumanResources\Repositories\CommissionRepository;
use Modules\HumanResources\