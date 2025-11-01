<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Collections\Models\PaymentSchedule;

try {
    echo "=== Payment Schedules Data ===\n";
    $schedules = PaymentSchedule::select('schedule_id', 'contract_id', 'installment_number', 'due_date', 'amount', 'status')
        ->orderBy('contract_id')
        ->orderBy('due_date')
        ->get();
    
    if ($schedules->isEmpty()) {
        echo "No payment schedules found.\n";
    } else {
        echo "Found " . $schedules->count() . " payment schedules:\n\n";
        foreach ($schedules as $schedule) {
            echo "Schedule ID: {$schedule->schedule_id}\n";
            echo "Contract ID: {$schedule->contract_id}\n";
            echo "Installment: {$schedule->installment_number}\n";
            echo "Due Date: {$schedule->due_date}\n";
            echo "Amount: {$schedule->amount}\n";
            echo "Status: {$schedule->status}\n";
            echo "---\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}