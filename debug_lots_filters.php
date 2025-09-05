<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Lot;
use Modules\Inventory\Repositories\LotRepository;

echo "=== DEBUG FILTROS DE LOTES ===\n\n";

try {
    // Verificar lotes existentes
    $totalLots = Lot::count();
    echo "Total de lotes en la base de datos: {$totalLots}\n\n";
    
    // Verificar lotes con manzana_id = 6
    $lotsWithManzana6 = Lot::where('manzana_id', 6)->count();
    echo "Lotes con manzana_id = 6: {$lotsWithManzana6}\n\n";
    
    // Verificar lotes con status = 'available'
    $availableLots = Lot::where('status', 'available')->count();
    echo "Lotes con status = 'available': {$availableLots}\n\n";
    
    // Verificar lotes con ambos filtros
    $filteredLots = Lot::where('manzana_id', 6)
                       ->where('status', 'available')
                       ->count();
    echo "Lotes con manzana_id = 6 Y status = 'available': {$filteredLots}\n\n";
    
    // Mostrar algunos lotes de ejemplo
    echo "=== PRIMEROS 5 LOTES ===\n";
    $sampleLots = Lot::select('lot_id', 'num_lot', 'manzana_id', 'status')
                     ->limit(5)
                     ->get();
    
    foreach ($sampleLots as $lot) {
        echo "ID: {$lot->lot_id}, Número: {$lot->num_lot}, Manzana ID: {$lot->manzana_id}, Estado: {$lot->status}\n";
    }
    
    echo "\n=== LOTES DE MANZANA 6 ===\n";
    $manzana6Lots = Lot::select('lot_id', 'num_lot', 'manzana_id', 'status')
                       ->where('manzana_id', 6)
                       ->limit(5)
                       ->get();
    
    if ($manzana6Lots->count() > 0) {
        foreach ($manzana6Lots as $lot) {
            echo "ID: {$lot->lot_id}, Número: {$lot->num_lot}, Manzana ID: {$lot->manzana_id}, Estado: {$lot->status}\n";
        }
    } else {
        echo "No se encontraron lotes con manzana_id = 6\n";
    }
    
    echo "\n=== PRUEBA DEL REPOSITORIO ===\n";
    $repository = new LotRepository();
    
    // Simular los filtros de la API
    $filters = [
        'per_page' => 100,
        'status' => 'available',
        'manzana_id' => 6
    ];
    
    echo "Filtros aplicados: " . json_encode($filters) . "\n";
    
    $result = $repository->paginate($filters, 100);
    
    echo "Resultados del repositorio:\n";
    echo "- Total: {$result->total()}\n";
    echo "- Por página: {$result->perPage()}\n";
    echo "- Página actual: {$result->currentPage()}\n";
    echo "- Items en esta página: {$result->count()}\n";
    
    if ($result->count() > 0) {
        echo "\n=== PRIMEROS RESULTADOS ===\n";
        foreach ($result->take(3) as $lot) {
            echo "ID: {$lot->lot_id}, Número: {$lot->num_lot}, Manzana ID: {$lot->manzana_id}, Estado: {$lot->status}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DEBUG ===\n";