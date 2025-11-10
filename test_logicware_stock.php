<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 Probando LogicWare API - getStockByStage\n";
echo "==========================================\n\n";

try {
    $service = app('App\Services\LogicwareApiService');
    
    echo "1. Generando token...\n";
    $token = $service->generateToken();
    echo "✅ Token generado: " . substr($token, 0, 50) . "...\n\n";
    
    echo "2. Obteniendo stock de ETAPA 1 (stageId=1)...\n";
    $stock = $service->getStockByStage('casabonita', '1', true); // force refresh
    
    echo "✅ Respuesta recibida:\n";
    echo "   - Success: " . ($stock['succeeded'] ?? 'NO') . "\n";
    echo "   - Total unidades: " . (isset($stock['data']) ? count($stock['data']) : 0) . "\n";
    echo "   - Is Mock: " . (isset($stock['meta']['is_mock']) && $stock['meta']['is_mock'] ? 'SI ⚠️' : 'NO ✅') . "\n\n";
    
    if (isset($stock['data']) && count($stock['data']) > 0) {
        echo "📦 Primeras 3 unidades (campos mapeados):\n\n";
        for ($i = 0; $i < min(3, count($stock['data'])); $i++) {
            $unit = $stock['data'][$i];
            echo "   " . ($i + 1) . ". Código: " . ($unit['code'] ?? 'N/A') . "\n";
            echo "      Nombre: " . ($unit['name'] ?? 'N/A') . "\n";
            echo "      Manzana: " . ($unit['block'] ?? 'N/A') . "\n";
            echo "      Área: " . ($unit['area'] ?? 0) . " m²\n";
            echo "      Precio: " . ($unit['currency'] ?? 'PEN') . " " . number_format($unit['price'] ?? 0, 2) . "\n";
            echo "      Status: " . ($unit['status'] ?? 'N/A') . "\n";
            echo "\n";
        }
    }
    
    echo "\n✅ Prueba completada\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
