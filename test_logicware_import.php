<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\LogicwareLotImportService;
use App\Services\LogicwareApiService;

echo "🧪 Probando importación de lotes desde LogicWare\n";
echo "================================================\n\n";

try {
    $logicwareApi = app(LogicwareApiService::class);
    $importService = app(LogicwareLotImportService::class);
    
    echo "1. Obteniendo stock de ETAPA 1...\n";
    $stock = $logicwareApi->getStockByStage('casabonita', '1', true);
    
    if (!isset($stock['data']) || count($stock['data']) === 0) {
        echo "❌ No hay unidades para importar\n";
        exit(1);
    }
    
    echo "✅ Stock obtenido: " . count($stock['data']) . " unidades\n\n";
    
    echo "2. Importando TODOS los lotes de ETAPA 1...\n";
    
    $options = [
        'update_existing' => true,
        'create_manzanas' => true,
        'create_templates' => true,
        'update_templates' => true,
        'update_status' => true
    ];
    
    $result = $importService->importLotsByStage('casabonita', '1', $options);
    
    echo "\n✅ Resultado de la importación:\n";
    echo "   - Total procesados: " . $result['stats']['total'] . "\n";
    echo "   - Creados: " . $result['stats']['created'] . "\n";
    echo "   - Actualizados: " . $result['stats']['updated'] . "\n";
    echo "   - Omitidos: " . $result['stats']['skipped'] . "\n";
    echo "   - Errores: " . $result['stats']['errors'] . "\n\n";
    
    if ($result['stats']['errors'] > 0) {
        echo "❌ Primeros 3 errores:\n";
        foreach (array_slice($result['errors'], 0, 3) as $error) {
            echo "   - " . $error['unit'] . ": " . $error['error'] . "\n";
        }
    } else {
        echo "🎉 ¡Importación exitosa sin errores!\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
