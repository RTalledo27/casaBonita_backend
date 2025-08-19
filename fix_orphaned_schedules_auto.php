<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\Models\Contract;
use Modules\Sales\Models\PaymentSchedule;
use Illuminate\Support\Facades\DB;

try {
    echo "=== Fixing Orphaned Payment Schedules (AUTO) ===\n";
    
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
        
        echo "\n🔄 Proceeding with automatic cleanup...\n";
        
        DB::beginTransaction();
        
        try {
            // Delete orphaned schedules
            $deletedCount = PaymentSchedule::whereNotIn('contract_id', $existingContractIds)->delete();
            
            DB::commit();
            echo "✅ Successfully deleted {$deletedCount} orphaned payment schedules\n";
            
            // Verify cleanup
            $remainingOrphaned = PaymentSchedule::whereNotIn('contract_id', $existingContractIds)->count();
            echo "Remaining orphaned schedules: {$remainingOrphaned}\n";
            
            if ($remainingOrphaned === 0) {
                echo "🎉 All orphaned payment schedules have been cleaned up!\n";
                echo "The validation error 'The selected contract id is invalid' should now be resolved.\n";
            }
            
        } catch (Exception $e) {
            DB::rollback();
            echo "❌ Error during deletion: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✅ No orphaned payment schedules found.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}