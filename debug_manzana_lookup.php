<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\Lot;

echo "=== DEBUG MANZANA LOOKUP ISSUES ===\n\n";

// 1. Mostrar todas las manzanas disponibles
echo "1. MANZANAS DISPONIBLES EN LA BASE DE DATOS:\n";
$manzanas = Manzana::orderBy('name')->get();
foreach ($manzanas as $manzana) {
    echo "   - ID: {$manzana->manzana_id}, Nombre: '{$manzana->name}'\n";
}

echo "\n2. BÚSQUEDAS ESPECÍFICAS DEL EXCEL:\n";

// 2. Buscar las manzanas específicas del Excel que están fallando
$searchTerms = ['manzana 2', 'manzana 3', '2', '3'];

foreach ($searchTerms as $term) {
    echo "\n   Buscando: '{$term}'\n";
    
    // Búsqueda exacta (como hace el código actual)
    $exactMatch = Manzana::where('name', $term)->first();
    echo "   - Búsqueda exacta: " . ($exactMatch ? "ENCONTRADA (ID: {$exactMatch->manzana_id})" : "NO ENCONTRADA") . "\n";
    
    // Búsqueda con LIKE (más flexible)
    $likeMatches = Manzana::where('name', 'LIKE', "%{$term}%")->get();
    echo "   - Búsqueda LIKE: " . ($likeMatches->count() > 0 ? "ENCONTRADAS ({$likeMatches->count()})" : "NO ENCONTRADAS") . "\n";
    
    if ($likeMatches->count() > 0) {
        foreach ($likeMatches as $match) {
            echo "     * ID: {$match->manzana_id}, Nombre: '{$match->name}'\n";
        }
    }
}

echo "\n3. LOTES ESPECÍFICOS DEL EXCEL:\n";

// 3. Verificar los lotes específicos que están fallando
$lotSearches = [
    ['manzana' => 'manzana 2', 'lote' => 'B'],
    ['manzana' => 'manzana 3', 'lote' => 'C'],
    ['manzana' => '2', 'lote' => 'B'],
    ['manzana' => '3', 'lote' => 'C']
];

foreach ($lotSearches as $search) {
    echo "\n   Buscando lote '{$search['lote']}' en manzana '{$search['manzana']}':\n";
    
    // Buscar manzana primero
    $manzana = Manzana::where('name', $search['manzana'])->first();
    
    if ($manzana) {
        echo "   - Manzana encontrada: ID {$manzana->manzana_id}\n";
        
        // Buscar lote
        $lot = Lot::where('num_lot', $search['lote'])
                  ->where('manzana_id', $manzana->manzana_id)
                  ->with('financialTemplate')
                  ->first();
        
        if ($lot) {
            echo "   - Lote encontrado: ID {$lot->lot_id}\n";
            echo "   - Tiene template financiero: " . ($lot->financialTemplate ? 'SÍ' : 'NO') . "\n";
            
            if ($lot->financialTemplate) {
                $template = $lot->financialTemplate;
                echo "   - Template ID: {$template->template_id}\n";
                echo "   - Precio lista: {$template->precio_lista}\n";
                echo "   - Cuota inicial: {$template->initial_payment}\n";
            }
        } else {
            echo "   - Lote NO encontrado\n";
            
            // Mostrar lotes disponibles en esta manzana
            $availableLots = Lot::where('manzana_id', $manzana->manzana_id)->get();
            echo "   - Lotes disponibles en esta manzana: ";
            if ($availableLots->count() > 0) {
                echo "\n";
                foreach ($availableLots as $availableLot) {
                    echo "     * {$availableLot->num_lot}\n";
                }
            } else {
                echo "NINGUNO\n";
            }
        }
    } else {
        echo "   - Manzana NO encontrada\n";
        
        // Buscar manzanas similares
        $similarManzanas = Manzana::where('name', 'LIKE', "%{$search['manzana']}%")->get();
        if ($similarManzanas->count() > 0) {
            echo "   - Manzanas similares encontradas:\n";
            foreach ($similarManzanas as $similar) {
                echo "     * ID: {$similar->manzana_id}, Nombre: '{$similar->name}'\n";
            }
        }
    }
}

echo "\n=== FIN DEL DEBUG ===\n";