<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;

echo "=== DEBUG: BÚSQUEDA DE LOTES ===\n\n";

// Datos que se están enviando desde el Excel
$testData = [
    ['lot_number' => '1', 'lot_manzana' => '1'],
    ['lot_number' => '2', 'lot_manzana' => '1']
];

foreach ($testData as $index => $data) {
    echo "--- Probando lote " . ($index + 1) . " ---\n";
    echo "Buscando: num_lot = '{$data['lot_number']}', manzana_id = '{$data['lot_manzana']}'\n";
    
    // Buscar el lote exactamente como lo hace findLotWithFinancialTemplate
    $lot = Lot::where('num_lot', $data['lot_number'])
                ->where('manzana_id', $data['lot_manzana'])
                ->first();
    
    if ($lot) {
        echo "✓ Lote encontrado: ID {$lot->lot_id}, Número {$lot->num_lot}, Manzana ID {$lot->manzana_id}\n";
        
        // Verificar template financiero
        $template = $lot->lotFinancialTemplate;
        if ($template) {
            echo "✓ Template financiero encontrado: ID {$template->id}\n";
        } else {
            echo "✗ NO tiene template financiero\n";
            
            // Buscar templates disponibles para este lote
            $availableTemplates = LotFinancialTemplate::where('lot_id', $lot->lot_id)->get();
            echo "Templates disponibles para este lote: " . $availableTemplates->count() . "\n";
            
            if ($availableTemplates->count() > 0) {
                foreach ($availableTemplates as $tmpl) {
                    echo "  - Template ID: {$tmpl->id}, Lot ID: {$tmpl->lot_id}\n";
                }
            }
        }
    } else {
        echo "✗ Lote NO encontrado\n";
        
        // Buscar lotes similares
        echo "Buscando lotes con num_lot = '{$data['lot_number']}':\n";
        $similarLots = Lot::where('num_lot', $data['lot_number'])->get();
        foreach ($similarLots as $similar) {
            echo "  - Lote ID {$similar->lot_id}: num_lot={$similar->num_lot}, manzana_id={$similar->manzana_id}\n";
        }
        
        echo "Buscando lotes con manzana_id = '{$data['lot_manzana']}':\n";
        $manzanaLots = Lot::where('manzana_id', $data['lot_manzana'])->take(5)->get();
        foreach ($manzanaLots as $manzana) {
            echo "  - Lote ID {$manzana->lot_id}: num_lot={$manzana->num_lot}, manzana_id={$manzana->manzana_id}\n";
        }
    }
    
    echo "\n";
}

echo "=== FIN DEBUG ===\n";