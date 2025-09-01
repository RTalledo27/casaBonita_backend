<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== FIXING CONTRACT RESERVATION REFERENCE ===\n";

// Get the contract
$contract = \DB::table('contracts')->where('contract_id', 1)->first();
echo "Current contract reservation_id: " . $contract->reservation_id . "\n";

// Get the first available reservation
$firstReservation = \DB::table('reservations')->orderBy('reservation_id')->first();
echo "First available reservation_id: " . $firstReservation->reservation_id . "\n";
echo "Client ID: " . $firstReservation->client_id . "\n";
echo "Lot ID: " . $firstReservation->lot_id . "\n";

// Update the contract to point to an existing reservation
$updated = \DB::table('contracts')
    ->where('contract_id', 1)
    ->update(['reservation_id' => $firstReservation->reservation_id]);

if ($updated) {
    echo "Contract updated successfully!\n";
    
    // Test the relationship now
    echo "\n=== TESTING UPDATED RELATIONSHIP ===\n";
    $contract = \Modules\Sales\Models\Contract::with(['reservation.client', 'reservation.lot'])->first();
    
    if ($contract && $contract->reservation) {
        echo "Reservation found: " . $contract->reservation->reservation_id . "\n";
        
        if ($contract->reservation->client) {
            echo "Client Name: " . $contract->reservation->client->first_name . " " . $contract->reservation->client->last_name . "\n";
        }
        
        if ($contract->reservation->lot) {
            echo "Lot Number: " . $contract->reservation->lot->num_lot . "\n";
            echo "Lot Area: " . $contract->reservation->lot->area_m2 . "\n";
        }
    }
    
    // Test PaymentScheduleResource
    echo "\n=== TESTING PAYMENT SCHEDULE RESOURCE ===\n";
    $schedule = \Modules\Sales\Models\PaymentSchedule::with([
        'contract.reservation.client', 
        'contract.reservation.lot'
    ])->first();
    
    if ($schedule) {
        $resource = new \Modules\Sales\Transformers\PaymentScheduleResource($schedule);
        $resourceArray = $resource->toArray(request());
        
        echo "Client Name: " . ($resourceArray['client_name'] ?? 'N/A') . "\n";
        echo "Client Document: " . ($resourceArray['client_document'] ?? 'N/A') . "\n";
        echo "Lot Number: " . ($resourceArray['lot_number'] ?? 'N/A') . "\n";
        echo "Lot Area: " . ($resourceArray['lot_area'] ?? 'N/A') . "\n";
    }
    
} else {
    echo "Failed to update contract\n";
}