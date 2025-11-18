<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Services\PayrollCalculationService;

$calc = app(PayrollCalculationService::class);
$employee = Employee::find(1);

echo "Empleado: {$employee->user->name}\n";
echo "Pension System: {$employee->pension_system}\n";
echo "AFP Provider: " . ($employee->afp_provider ?? 'NULL') . "\n\n";

$result = $calc->calculatePayroll(
    employee: $employee,
    baseSalary: 1000,
    commissionsAmount: 0,
    bonusesAmount: 0,
    overtimeAmount: 0,
    year: 2025
);

echo "RESULTADOS DEL CÁLCULO:\n";
echo json_encode($result, JSON_PRETTY_PRINT);
