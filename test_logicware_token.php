<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\LogicwareApiService;

try {
    $service = app(LogicwareApiService::class);
    
    echo "=== TEST LOGICWARE TOKEN ===\n";
    
    // Test 1: Generar/obtener token
    echo "\n1. Obteniendo token del caché...\n";
    $token = $service->generateToken(false);
    echo "   Token obtenido: " . substr($token, 0, 50) . "...\n";
    echo "   Longitud: " . strlen($token) . " caracteres\n";
    
    // Test 2: Intentar obtener stock completo
    echo "\n2. Intentando obtener stock completo...\n";
    try {
        $stockData = $service->getFullStockData(false);
        echo "   ✅ Stock obtenido exitosamente\n";
        echo "   Total unidades: " . (isset($stockData['data']) ? count($stockData['data']) : 0) . "\n";
    } catch (Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== FIN TEST ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
