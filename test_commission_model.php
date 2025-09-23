<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "=== TESTING COMMISSION MODEL ===\n";
    
    // Test 1: Check if Commission model exists
    if (class_exists('Modules\\HumanResources\\Models\\Commission')) {
        echo "✓ Commission model class exists\n";
    } else {
        echo "✗ Commission model class NOT found\n";
        exit(1);
    }
    
    // Test 2: Try to query commissions
    $commission = \Modules\HumanResources\Models\Commission::find(7);
    
    if ($commission) {
        echo "✓ Commission ID 7 found\n";
        echo "  - Status: {$commission->status}\n";
        echo "  - Payment Part: {$commission->payment_part}\n";
        echo "  - Contract ID: {$commission->contract_id}\n";
        echo "  - Requires Verification: " . ($commission->requires_client_payment_verification ? 'Yes' : 'No') . "\n";
        echo "  - Is Eligible: " . ($commission->is_eligible_for_payment ? 'Yes' : 'No') . "\n";
    } else {
        echo "✗ Commission ID 7 NOT found\n";
    }
    
    // Test 3: Check database connection
    $count = \Modules\HumanResources\Models\Commission::count();
    echo "✓ Total commissions in database: {$count}\n";
    
    echo "\n=== ALL TESTS COMPLETED ===\n";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}