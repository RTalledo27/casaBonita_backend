<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Models\Commission;
use Illuminate\Support\Facades\DB;

try {
    echo "=== COMMISSION DATABASE ANALYSIS ===\n";
    
    // Get all commissions for July 2025
    $commissions = Commission::with(['employee.user'])
        ->where('period_month', 7)
        ->where('period_year', 2025)
        ->get();
    
    echo "Total commissions found: " . $commissions->count() . "\n\n";
    
    $pendingCount = 0;
    $pendingTotal = 0;
    $paidCount = 0;
    $paidTotal = 0;
    
    foreach ($commissions as $commission) {
        echo "Commission ID: {$commission->commission_id}\n";
        echo "  Employee: {$commission->employee->user->first_name} {$commission->employee->user->last_name}\n";
        echo "  Status: {$commission->payment_status}\n";
        echo "  Amount: {$commission->commission_amount}\n";
        echo "  Amount Type: " . gettype($commission->commission_amount) . "\n";
        
        if ($commission->payment_status === 'pendiente') {
            $pendingCount++;
            $pendingTotal += floatval($commission->commission_amount);
        } elseif ($commission->payment_status === 'pagado') {
            $paidCount++;
            $paidTotal += floatval($commission->commission_amount);
        }
        echo "\n";
    }
    
    echo "=== SUMMARY ===\n";
    echo "Pending count: $pendingCount\n";
    echo "Pending total: $pendingTotal\n";
    echo "Paid count: $paidCount\n";
    echo "Paid total: $paidTotal\n";
    
    // Also check raw database values
    echo "\n=== RAW DATABASE CHECK ===\n";
    $rawPending = DB::table('commissions')
        ->where('period_month', 7)
        ->where('period_year', 2025)
        ->where('payment_status', 'pendiente')
        ->get(['commission_id', 'commission_amount', 'payment_status']);
    
    echo "Raw pending commissions from DB:\n";
    foreach ($rawPending as $raw) {
        echo "ID: {$raw->commission_id}, Amount: {$raw->commission_amount}, Status: {$raw->payment_status}\n";
    }
    
    $rawPendingSum = DB::table('commissions')
        ->where('period_month', 7)
        ->where('period_year', 2025)
        ->where('payment_status', 'pendiente')
        ->sum('commission_amount');
    
    echo "\nRaw pending sum from DB: $rawPendingSum\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}