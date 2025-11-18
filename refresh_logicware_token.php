<?php

require_once 'vendor/autoload.php';

use App\Services\LogicwareApiService;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔄 Renovando token de Logicware...\n\n";

try {
    $apiService = app(LogicwareApiService::class);
    
    // Renovar token
    $token = $apiService->refreshToken();
    
    echo "✅ Token renovado exitosamente\n\n";
    echo "📄 Token preview: " . substr($token, 0, 50) . "...\n\n";
    
    // Verificar requests disponibles
    $used = $apiService->getDailyRequestCount();
    $remaining = 4 - $used;
    
    echo "📊 Estado de requests:\n";
    echo "   • Usados hoy: {$used}\n";
    echo "   • Disponibles: {$remaining}\n\n";
    
    if ($remaining <= 0) {
        echo "⚠️  No hay requests disponibles hasta mañana\n";
    } else {
        echo "✅ Puedes hacer {$remaining} importaciones más hoy\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
