<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\LogicwareApiService;
use Illuminate\Support\Facades\Cache;

echo "=== FORZAR CARGA DE STOCK AL CACHÉ ===\n\n";

try {
    $service = app(LogicwareApiService::class);
    
    // Verificar requests disponibles
    $today = date('Y-m-d');
    $dailyRequests = Cache::get("logicware_daily_requests_{$today}", 0);
    
    echo "📊 Requests usados: {$dailyRequests}/4\n";
    echo "📊 Requests disponibles: " . (4 - $dailyRequests) . "\n\n";
    
    if ($dailyRequests >= 4) {
        echo "❌ Ya se alcanzó el límite diario de 4 requests\n";
        echo "⏰ Espera hasta mañana para hacer nuevas consultas\n";
        exit(1);
    }
    
    echo "🔄 Consultando API de Logicware...\n";
    echo "⚠️  Esto consumirá 1 request del límite diario\n\n";
    
    $stockData = $service->getFullStockData(true); // Force refresh
    
    $unitsCount = count($stockData['data'] ?? []);
    
    echo "✅ STOCK CARGADO EXITOSAMENTE\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Unidades obtenidas: {$unitsCount}\n";
    echo "Guardado en caché: " . ($stockData['cached_at'] ?? 'N/A') . "\n";
    echo "Expira en: 6 horas\n";
    echo "Requests usados: " . ($stockData['daily_requests_used'] ?? 'N/A') . "/4\n\n";
    
    echo "✨ El stock ahora está disponible en el frontend\n";
    echo "🔄 Recarga la página: http://localhost:4200/sales/contracts\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    exit(1);
}

echo "\n=== FIN ===\n";
