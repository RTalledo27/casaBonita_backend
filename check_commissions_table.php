<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "Columnas en la tabla commissions:\n";
$columns = Schema::getColumnListing('commissions');
foreach($columns as $column) {
    echo "- $column\n";
}

echo "\n\nÚltimas 3 comisiones con campos relevantes:\n";
try {
    $commissions = DB::table('commissions')
        ->select(['commission_id', 'commission_period', 'payment_period', 'payment_percentage', 'status', 'parent_commission_id', 'payment_part', 'payment_status', 'payment_type'])
        ->latest('commission_id')
        ->take(3)
        ->get();
    
    foreach($commissions as $commission) {
        echo "Commission ID: {$commission->commission_id}\n";
        echo "Commission Period: " . ($commission->commission_period ?? 'NULL') . "\n";
        echo "Payment Period: " . ($commission->payment_period ?? 'NULL') . "\n";
        echo "Payment Percentage: " . ($commission->payment_percentage ?? 'NULL') . "\n";
        echo "Status: " . ($commission->status ?? 'NULL') . "\n";
        echo "Parent Commission ID: " . ($commission->parent_commission_id ?? 'NULL') . "\n";
        echo "Payment Part: " . ($commission->payment_part ?? 'NULL') . "\n";
        echo "Payment Status: " . ($commission->payment_status ?? 'NULL') . "\n";
        echo "Payment Type: " . ($commission->payment_type ?? 'NULL') . "\n";
        echo "---\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}