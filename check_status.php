<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\Models\Contract;

try {
    echo "Status values in contracts table:\n";
    $statuses = Contract::select('status')->distinct()->get();
    
    foreach($statuses as $status) {
        echo "- " . $status->status . "\n";
    }
    
    echo "\nContracts with financing_amount > 0 by status:\n";
    $contractsByStatus = Contract::where('financing_amount', '>', 0)
        ->select('status', \DB::raw('count(*) as count'))
        ->groupBy('status')
        ->get();
    
    foreach($contractsByStatus as $item) {
        echo "- " . $item->status . ": " . $item->count . " contracts\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}