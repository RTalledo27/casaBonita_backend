<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ELIMINANDO CONTRATOS Y DATOS RELACIONADOS ===\n\n";

try {
    DB::beginTransaction();
    
    // Deshabilitar foreign key checks temporalmente
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    
    // 1. Eliminar payments primero
    $payments = DB::table('payments')->count();
    DB::table('payments')->truncate();
    echo "✅ Eliminados {$payments} pagos\n";
    
    // 2. Eliminar payment_schedules
    $schedules = DB::table('payment_schedules')->count();
    DB::table('payment_schedules')->truncate();
    echo "✅ Eliminados {$schedules} cronogramas de pago\n";
    
    // 3. Eliminar contracts
    $contracts = DB::table('contracts')->count();
    DB::table('contracts')->truncate();
    echo "✅ Eliminados {$contracts} contratos\n";
    
    // 4. Eliminar clients
    $clients = DB::table('clients')->count();
    DB::table('clients')->truncate();
    echo "✅ Eliminados {$clients} clientes\n";
    
    // 5. Limpiar cache de Logicware
    DB::table('cache')->where('key', 'like', 'logicware_%')->delete();
    echo "✅ Cache de Logicware limpiado\n";
    
    // Rehabilitar foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    
    DB::commit();
    
    echo "\n🎉 Base de datos limpia y lista para nueva importación\n";
    echo "\n💡 Ahora puedes importar desde el frontend\n";
    
} catch (Exception $e) {
    DB::rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
}
