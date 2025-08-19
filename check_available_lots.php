<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Lot;

echo "=== LOTES DISPONIBLES ===\n\n";

try {
    $lots = Lot::select('lot_id', 'num_lot', 'manzana_id', 'status')
               ->limit(10)
               ->get();
    
    if ($lots->count() > 0) {
        echo "Primeros 10 lotes encontrados:\n";
        foreach ($lots as $lot) {
            echo "ID: {$lot->lot_id}, Número: {$lot->num_lot}, Manzana ID: {$lot->manzana_id}, Estado: {$lot->status}\n";
        }
    } else {
        echo "No se encontraron lotes en la base de datos.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN ===\n";