<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== VERIFICACIÓN DE DATOS ===\n\n";

$contracts = DB::table('contracts')->count();
$clients = DB::table('clients')->count();
$schedules = DB::table('payment_schedules')->count();
$payments = DB::table('payments')->count();

echo "📊 Estado actual de la base de datos:\n";
echo "   - Contratos: {$contracts}\n";
echo "   - Clientes: {$clients}\n";
echo "   - Cronogramas de pago: {$schedules}\n";
echo "   - Pagos: {$payments}\n\n";

if ($contracts == 0 && $clients == 0 && $schedules == 0) {
    echo "✅ Base de datos limpia y lista para importar desde el frontend\n";
} else {
    echo "⚠️ Aún hay datos en la base de datos\n";
}
