<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Lot;

echo "=== ESTADOS ÚNICOS DE LOTES ===\n\n";

try {
    $statuses = Lot::select('status')->distinct()->pluck('status');
    
    echo "Estados encontrados en la base de datos:\n";
    foreach($statuses as $status) {
        $count = Lot::where('status', $status)->count();
        echo "- '{$status}': {$count} lotes\n";
    }
    
    echo "\n=== LOTES DE MANZANA 6 POR ESTADO ===\n";
    $manzana6Statuses = Lot::where('manzana_id', 6)
                           ->select('status')
                           ->distinct()
                           ->pluck('status');
    
    foreach($manzana6Statuses as $status) {
        $count = Lot::where('manzana_id', 6)->where('status', $status)->count();
        echo "- '{$status}': {$count} lotes en manzana 6\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN ===\n";