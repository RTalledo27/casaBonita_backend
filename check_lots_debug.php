<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\Lot;

echo "=== VERIFICACIÓN DE LOTES Y MANZANAS ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Mostrar todas las manzanas
echo "🏘️ MANZANAS DISPONIBLES:\n";
$manzanas = Manzana::all(['manzana_id', 'name']);
foreach ($manzanas as $manzana) {
    echo "  - ID: {$manzana->manzana_id}, Nombre: '{$manzana->name}'\n";
}

// 2. Buscar manzanas específicas del Excel
echo "\n🔍 BUSCANDO MANZANAS DEL EXCEL:\n";
$testManzanas = ['2', 'B', '3', 'C'];
foreach ($testManzanas as $testName) {
    $found = Manzana::where('name', $testName)->first();
    if ($found) {
        echo "  ✅ Manzana '{$testName}' encontrada - ID: {$found->manzana_id}\n";
    } else {
        echo "  ❌ Manzana '{$testName}' NO encontrada\n";
    }
}

// 3. Mostrar algunos lotes con sus manzanas
echo "\n📦 LOTES DISPONIBLES (primeros 10):\n";
$lots = Lot::with('manzana')->take(10)->get();
foreach ($lots as $lot) {
    $manzanaName = $lot->manzana ? $lot->manzana->name : 'SIN_MANZANA';
    echo "  - Lote: {$lot->num_lot}, Manzana: '{$manzanaName}', ID: {$lot->lot_id}\n";
}

// 4. Buscar lotes específicos del Excel
echo "\n🔍 BUSCANDO LOTES ESPECÍFICOS DEL EXCEL:\n";
$testLots = [
    ['numero' => 'B', 'manzana' => '2'],
    ['numero' => 'C', 'manzana' => '3']
];

foreach ($testLots as $testLot) {
    echo "\nBuscando lote '{$testLot['numero']}' en manzana '{$testLot['manzana']}':\n";
    
    // Buscar manzana primero
    $manzana = Manzana::where('name', $testLot['manzana'])->first();
    if (!$manzana) {
        echo "  ❌ Manzana '{$testLot['manzana']}' no encontrada\n";
        continue;
    }
    
    // Buscar lote
    $lot = Lot::where('num_lot', $testLot['numero'])
                ->where('manzana_id', $manzana->manzana_id)
                ->first();
    
    if ($lot) {
        echo "  ✅ Lote encontrado - ID: {$lot->lot_id}\n";
        echo "  📋 ¿Tiene template financiero? " . ($lot->financialTemplate ? 'SÍ' : 'NO') . "\n";
    } else {
        echo "  ❌ Lote '{$testLot['numero']}' no encontrado en manzana '{$testLot['manzana']}'\n";
        
        // Mostrar lotes disponibles en esa manzana
        $lotsInManzana = Lot::where('manzana_id', $manzana->manzana_id)->get();
        echo "  📋 Lotes disponibles en manzana '{$testLot['manzana']}': ";
        if ($lotsInManzana->count() > 0) {
            echo implode(', ', $lotsInManzana->pluck('num_lot')->toArray()) . "\n";
        } else {
            echo "NINGUNO\n";
        }
    }
}

echo "\n=== FIN DE LA VERIFICACIÓN ===\n";