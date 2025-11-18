<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Cache;

echo "=== ESTADO CACHÉ LOGICWARE ===\n\n";

// 1. Verificar caché de full stock
$fullStockCache = Cache::get('logicware_full_stock_casabonita');
if ($fullStockCache) {
    echo "✅ Caché de stock EXISTE\n";
    echo "   Unidades: " . count($fullStockCache['data'] ?? []) . "\n";
    echo "   Guardado: " . ($fullStockCache['cached_at'] ?? 'N/A') . "\n";
    echo "   Expira: " . ($fullStockCache['cache_expires_at'] ?? 'N/A') . "\n";
} else {
    echo "❌ Caché de stock NO existe\n";
}

echo "\n";

// 2. Verificar requests diarios
$today = date('Y-m-d');
$dailyRequests = Cache::get("logicware_daily_requests_{$today}", 0);
echo "📊 Requests API usados hoy ({$today}): {$dailyRequests} de 4\n";

// 3. Verificar token
$token = Cache::get('logicware_bearer_token_casabonita');
if ($token) {
    echo "✅ Token existe: " . substr($token, 0, 50) . "...\n";
} else {
    echo "❌ Token NO existe\n";
}

echo "\n=== FIN ===\n";
