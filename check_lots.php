<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Lot;

echo "=== Checking Lots in Database ===\n\n";

$totalLots = Lot::count();
echo "Total lots in database: {$totalLots}\n\n";

if ($totalLots > 0) {
    echo "First 10 lots:\n";
    $lots = Lot::take(10)->get(['num_lot', 'manzana_id', 'status']);
    foreach ($lots as $lot) {
        echo "- Lot {$lot->num_lot}, Manzana ID {$lot->manzana_id}, Status: {$lot->status}\n";
    }
    
    echo "\nChecking for lots 101, 102, 103:\n";
    $testLots = Lot::whereIn('num_lot', ['101', '102', '103'])->get(['num_lot', 'manzana_id', 'status']);
    if ($testLots->count() > 0) {
        foreach ($testLots as $lot) {
            echo "- Found Lot {$lot->num_lot}, Manzana ID {$lot->manzana_id}, Status: {$lot->status}\n";
        }
    } else {
        echo "- No lots found with numbers 101, 102, 103\n";
        echo "\nLet's use existing lots for testing. Available lots:\n";
        $availableLots = Lot::where('status', 'disponible')->take(3)->get(['num_lot', 'manzana_id', 'status']);
        foreach ($availableLots as $lot) {
            echo "- Available Lot {$lot->num_lot}, Manzana ID {$lot->manzana_id}\n";
        }
    }
} else {
    echo "No lots found in database. Need to create some test lots.\n";
}

echo "\n=== Check Complete ===\n";