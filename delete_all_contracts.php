<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🗑️  ELIMINANDO TODOS LOS CONTRATOS Y SUS RELACIONES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    DB::beginTransaction();
    
    // 1. Contar antes de borrar
    $totalContracts = DB::table('contracts')->count();
    $totalSchedules = DB::table('payment_schedules')->count();
    $totalPayments = DB::table('payments')->count();
    
    echo "📊 Estado antes de borrar:\n";
    echo "   • Contratos: {$totalContracts}\n";
    echo "   • Cronogramas de pago: {$totalSchedules}\n";
    echo "   • Pagos registrados: {$totalPayments}\n\n";
    
    // 2. Borrar pagos primero (dependen de payment_schedules)
    if ($totalPayments > 0) {
        echo "🗑️  Borrando pagos...\n";
        DB::table('payments')->delete();
        echo "   ✅ {$totalPayments} pagos eliminados\n\n";
    }
    
    // 3. Borrar cronogramas de pago (dependen de contracts)
    if ($totalSchedules > 0) {
        echo "🗑️  Borrando cronogramas de pago...\n";
        DB::table('payment_schedules')->delete();
        echo "   ✅ {$totalSchedules} cronogramas eliminados\n\n";
    }
    
    // 4. Borrar contratos
    if ($totalContracts > 0) {
        echo "🗑️  Borrando contratos...\n";
        DB::table('contracts')->delete();
        echo "   ✅ {$totalContracts} contratos eliminados\n\n";
    }
    
    // 5. Resetear auto-increment (opcional)
    echo "🔄 Reseteando auto-increment...\n";
    DB::statement('ALTER TABLE contracts AUTO_INCREMENT = 1');
    DB::statement('ALTER TABLE payment_schedules AUTO_INCREMENT = 1');
    DB::statement('ALTER TABLE payments AUTO_INCREMENT = 1');
    echo "   ✅ Auto-increment reseteado\n\n";
    
    DB::commit();
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ TODOS LOS CONTRATOS HAN SIDO ELIMINADOS\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "📝 Resumen:\n";
    echo "   • {$totalContracts} contratos eliminados\n";
    echo "   • {$totalSchedules} cronogramas eliminados\n";
    echo "   • {$totalPayments} pagos eliminados\n\n";
    
    echo "🚀 Ahora puedes importar desde Logicware con:\n";
    echo "   • Algoritmo mejorado de matching de asesores\n";
    echo "   • Campos 'source' y 'logicware_data' guardados\n";
    echo "   • Cronogramas de pago desde fecha de venta correcta\n\n";
    
} catch (Exception $e) {
    DB::rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
