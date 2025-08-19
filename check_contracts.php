<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\Models\Contract;
use Modules\Sales\Models\PaymentSchedule;

try {
    echo "=== Contracts Data ===\n";
    $contracts = Contract::select('contract_id', 'contract_number', 'sign_date', 'total_price', 'status')
        ->orderBy('contract_id')
        ->get();
    
    if ($contracts->isEmpty()) {
        echo "No contracts found.\n";
    } else {
        echo "Found " . $contracts->count() . " contracts:\n\n";
        foreach ($contracts as $contract) {
            echo "Contract ID: {$contract->contract_id}\n";
            echo "Contract Number: {$contract->contract_number}\n";
            echo "Sign Date: {$contract->sign_date}\n";
            echo "Total Price: {$contract->total_price}\n";
            echo "Status: {$contract->status}\n";
            echo "---\n";
        }
    }
    
    echo "\n=== Payment Schedules Contract IDs ===\n";
    $scheduleContractIds = PaymentSchedule::select('contract_id')
        ->distinct()
        ->orderBy('contract_id')
        ->pluck('contract_id')
        ->toArray();
    
    echo "Unique contract_ids in payment_schedules: " . implode(', ', $scheduleContractIds) . "\n";
    
    echo "\n=== Missing Contracts ===\n";
    $existingContractIds = $contracts->pluck('contract_id')->toArray();
    $missingContractIds = array_diff($scheduleContractIds, $existingContractIds);
    
    if (empty($missingContractIds)) {
        echo "No missing contracts found.\n";
    } else {
        echo "Contract IDs in payment_schedules but not in contracts table: " . implode(', ', $missingContractIds) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}