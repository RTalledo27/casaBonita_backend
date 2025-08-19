<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$output = "";

// Check what lots exist in the database
$output .= "=== CHECKING EXISTING LOTS ===\n";
$lots = \DB::table('lots')->select('lot_id', 'num_lot', 'manzana_id', 'area_m2', 'status')->limit(10)->get();
$output .= "Found " . $lots->count() . " lots:\n";
foreach ($lots as $lot) {
    $output .= "  Lot ID: {$lot->lot_id}, num_lot: {$lot->num_lot}, manzana_id: {$lot->manzana_id}, area_m2: {$lot->area_m2}, status: {$lot->status}\n";
}

// Check current reservation
$output .= "\n=== CURRENT RESERVATION DATA ===\n";
$reservation = \DB::table('reservations')->where('reservation_id', 26)->first();
if ($reservation) {
    $output .= "Reservation ID: {$reservation->reservation_id}\n";
    $output .= "Client ID: {$reservation->client_id}\n";
    $output .= "Lot ID: {$reservation->lot_id}\n";
    $output .= "Status: {$reservation->status}\n";
} else {
    $output .= "Reservation 26 not found\n";
}

// Update reservation to point to an existing lot
if ($lots->count() > 0) {
    $firstLot = $lots->first();
    $output .= "\n=== UPDATING RESERVATION TO USE EXISTING LOT ===\n";
    $output .= "Updating reservation 26 to use lot_id: {$firstLot->lot_id}\n";
    
    \DB::table('reservations')
        ->where('reservation_id', 26)
        ->update(['lot_id' => $firstLot->lot_id]);
    
    $output .= "Reservation updated successfully\n";
    
    // Test again with updated data
    $output .= "\n=== TESTING AFTER UPDATE ===\n";
    $schedule = \Modules\Sales\Models\PaymentSchedule::with([
        'contract.reservation.client', 
        'contract.reservation.lot'
    ])->first();
    
    if ($schedule && $schedule->contract && $schedule->contract->reservation) {
        $reservation = $schedule->contract->reservation;
        $output .= "Reservation lot_id: " . $reservation->lot_id . "\n";
        
        if ($reservation->lot) {
            $lot = $reservation->lot;
            $output .= "Lot loaded successfully!\n";
            $output .= "Lot ID: " . $lot->lot_id . "\n";
            $output .= "Lot num_lot: " . ($lot->num_lot ?? 'NULL') . "\n";
            $output .= "Lot manzana_id: " . ($lot->manzana_id ?? 'NULL') . "\n";
            $output .= "Lot area_m2: " . ($lot->area_m2 ?? 'NULL') . "\n";
        } else {
            $output .= "Lot still NULL after update\n";
        }
    }
    
    // Test PaymentScheduleResource with updated data
    $output .= "\n=== TESTING PAYMENT SCHEDULE RESOURCE AFTER UPDATE ===\n";
    if ($schedule) {
        try {
            $resource = new \Modules\Sales\Transformers\PaymentScheduleResource($schedule);
            $resourceArray = $resource->toArray(request());
            
            $output .= "Client Name: " . (is_object($resourceArray['client_name']) ? 'MissingValue' : $resourceArray['client_name']) . "\n";
            $output .= "Client Document: " . (is_object($resourceArray['client_document']) ? 'MissingValue' : $resourceArray['client_document']) . "\n";
            $output .= "Lot Number: " . (is_object($resourceArray['lot_number']) ? 'MissingValue' : $resourceArray['lot_number']) . "\n";
            $output .= "Lot Manzana: " . (is_object($resourceArray['lot_manzana']) ? 'MissingValue' : $resourceArray['lot_manzana']) . "\n";
            $output .= "Lot Area: " . (is_object($resourceArray['lot_area']) ? 'MissingValue' : $resourceArray['lot_area']) . "\n";
            
        } catch (Exception $e) {
            $output .= "Error creating resource: " . $e->getMessage() . "\n";
        }
    }
}

echo $output;
file_put_contents('test_output.txt', $output);
echo "\nOutput saved to test_output.txt\n";