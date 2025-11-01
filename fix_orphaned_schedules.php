<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\Models\Contract;
use Modules\Collections\Models\PaymentSchedule;
use Illuminate\Support\Facades\DB;

try {
    echo "=== Fixing Orphaned Payment Schedules ===\n";
    
    // Get existing contract IDs
    $existingContractIds = Contract::pluck('contract_id')->toArray();
    echo "Existing contract IDs: " . implode(', ', $existingContractIds) . "\n";
    
    // Get orphaned payment schedules
    $orphanedSchedules = PaymentSchedule::whereNotIn('contract_id', $existingContractIds)->get();
    echo "Found " . $orphanedSchedules->count() . " orphaned payment schedules\n";
    
    if ($orphanedSchedules->count() > 0) {
        echo "\nOrphaned schedules by contract_id:\n";
        $orphanedByContract = $orphanedSchedules->groupBy('contract_id');
        foreach ($orphanedByContract as $contractId => $schedules) {
            echo "Contract ID {$contractId}: {$schedules->count()} schedules\n";
        }
        
        echo "\n⚠️  WARNING: This will DELETE all orphaned payment schedules!\n";
        echo "Do you want to proceed? (y/N): ";
        
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim(strtolower($line)) === 'y') {
            DB::beginTransaction();
            
            try {
                // Delete orphaned schedules
                $deletedCount = PaymentSchedule::whereNotIn('contract_id', $existingContractIds)->delete();
                
                DB::commit();
                echo "✅ Successfully deleted {$deletedCount} orphaned payment schedules\n";
                
                // Verify cleanup
                $remainingOrphaned = PaymentSchedule::whereNotIn('contract_id', $existingContractIds)->count();
                echo "Remaining orphaned schedules: {$remainingOrphaned}\n";
                
            } catch (Exception $e) {
                DB::rollback();
                echo "❌ Error during deletion: " . $e->getMessage() . "\n";
            }
        } else {
            echo "Operation cancelled.\n";
        }
    } else {
        echo "✅ No orphaned payment schedules found.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}