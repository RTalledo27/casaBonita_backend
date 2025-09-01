<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\app\Models\Commission;

echo "=== Commission Analysis for June 2025 ===\n";

// Check payable commissions with amounts
$payableCommissions = Commission::where('commission_period', '2025-06')
    ->where('is_payable', true)
    ->select('id', 'employee_id', 'commission_amount', 'paid_amount', 'is_payable')
    ->take(10)
    ->get();

echo "Payable commissions (first 10):\n";
foreach ($payableCommissions as $commission) {
    echo "ID: {$commission->id}, Employee: {$commission->employee_id}, Amount: {$commission->commission_amount}, Paid: {$commission->paid_amount}, Payable: " . ($commission->is_payable ? 'true' : 'false') . "\n";
}

// Check total amounts
$totalPayable = Commission::where('commission_period', '2025-06')
    ->where('is_payable', true)
    ->sum('commission_amount');

$totalPaid = Commission::where('commission_period', '2025-06')
    ->where('is_payable', true)
    ->sum('paid_amount');

echo "\n=== Totals ===\n";
echo "Total payable amount: {$totalPayable}\n";
echo "Total paid amount: {$totalPaid}\n";
echo "Total pending: " . ($totalPayable - $totalPaid) . "\n";

// Check by employee
echo "\n=== By Employee ===\n";
$byEmployee = Commission::where('commission_period', '2025-06')
    ->where('is_payable', true)
    ->selectRaw('employee_id, SUM(commission_amount) as total_amount, SUM(paid_amount) as total_paid, COUNT(*) as count')
    ->groupBy('employee_id')
    ->get();

foreach ($byEmployee as $emp) {
    echo "Employee {$emp->employee_id}: {$emp->count} commissions, Total: {$emp->total_amount}, Paid: {$emp->total_paid}, Pending: " . ($emp->total_amount - $emp->total_paid) . "\n";
}