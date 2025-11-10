<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 Probando LogicWare API - getStages\n";
echo "=====================================\n\n";

try {
    $service = app('App\Services\LogicwareApiService');
    
    echo "1. Generando token...\n";
    $token = $service->generateToken();
    echo "✅ Token generado: " . substr($token, 0, 50) . "...\n\n";
    
    echo "2. Obteniendo stages...\n";
    $stages = $service->getStages('casabonita', true); // force refresh
    
    echo "✅ Respuesta recibida:\n";
    echo "   - Success: " . ($stages['succeeded'] ? 'SI' : 'NO') . "\n";
    echo "   - Total stages: " . (isset($stages['data']) ? count($stages['data']) : 0) . "\n\n";
    
    if (isset($stages['data']) && count($stages['data']) > 0) {
        echo "📋 Stages encontrados:\n";
        foreach ($stages['data'] as $index => $stage) {
            echo "   " . ($index + 1) . ". ID: " . ($stage['id'] ?? 'NO-ID') . "\n";
            echo "      Nombre: " . ($stage['name'] ?? 'N/A') . "\n";
            echo "      Código: " . ($stage['code'] ?? 'N/A') . "\n";
            echo "      Unidades: " . ($stage['units'] ?? 0) . "\n";
            echo "\n";
        }
    } else {
        echo "⚠️ No se encontraron stages\n";
    }
    
    echo "\n✅ Prueba completada exitosamente\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
