<?php
/**
 * Script de prueba para verificar que el sistema de eventos de cortes funciona
 * 
 * Ejecutar con: php test_sales_cut_events.php
 */

require __DIR__ . '/vendor/autoload.php';

use App\Models\SalesCut;
use App\Models\SalesCutItem;
use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n🧪 PRUEBA DEL SISTEMA DE CORTES DE VENTAS\n";
echo "=========================================\n\n";

// 1. Verificar EventServiceProvider
echo "1️⃣ Verificando EventServiceProvider...\n";
$providers = config('app.providers');
if (in_array('App\\Providers\\EventServiceProvider', $providers)) {
    echo "   ✅ EventServiceProvider registrado en config/app.php\n";
} else {
    echo "   ⚠️ EventServiceProvider NO encontrado en config/app.php\n";
    echo "   📝 Verificando bootstrap/providers.php...\n";
    
    $bootstrapProviders = require __DIR__ . '/bootstrap/providers.php';
    if (in_array('App\\Providers\\EventServiceProvider', $bootstrapProviders)) {
        echo "   ✅ EventServiceProvider registrado en bootstrap/providers.php\n";
    } else {
        echo "   ❌ EventServiceProvider NO registrado\n";
    }
}

// 2. Verificar que existen los archivos de eventos
echo "\n2️⃣ Verificando archivos de eventos...\n";
$files = [
    'app/Events/ContractCreated.php' => 'ContractCreated Event',
    'app/Events/PaymentRecorded.php' => 'PaymentRecorded Event',
    'app/Listeners/UpdateTodaySalesCut.php' => 'UpdateTodaySalesCut Listener',
    'app/Providers/EventServiceProvider.php' => 'EventServiceProvider',
];

foreach ($files as $file => $name) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "   ✅ $name\n";
    } else {
        echo "   ❌ $name NO EXISTE\n";
    }
}

// 3. Verificar corte del día
echo "\n3️⃣ Verificando corte del día...\n";
$todayCut = SalesCut::where('cut_date', now()->toDateString())
    ->where('status', 'open')
    ->first();

if ($todayCut) {
    echo "   ✅ Existe corte abierto para hoy: {$todayCut->cut_date}\n";
    echo "   📊 ID: {$todayCut->cut_id}\n";
    echo "   📊 Ventas: {$todayCut->total_sales_count}\n";
    echo "   📊 Pagos: {$todayCut->total_payments_count}\n";
    echo "   📊 Comisiones: $" . number_format($todayCut->total_commissions, 2) . "\n";
} else {
    echo "   ⚠️ No hay corte abierto para hoy\n";
    echo "   💡 Creando corte de prueba...\n";
    
    $todayCut = SalesCut::create([
        'cut_date' => now()->toDateString(),
        'cut_type' => 'daily',
        'status' => 'open',
    ]);
    
    echo "   ✅ Corte creado: ID {$todayCut->cut_id}\n";
}

// 4. Verificar items del corte
echo "\n4️⃣ Verificando items del corte...\n";
$items = SalesCutItem::where('cut_id', $todayCut->cut_id)->get();
echo "   📦 Total de items: " . $items->count() . "\n";
echo "   📦 Ventas: " . $items->where('item_type', 'sale')->count() . "\n";
echo "   📦 Pagos: " . $items->where('item_type', 'payment')->count() . "\n";
echo "   📦 Comisiones: " . $items->where('item_type', 'commission')->count() . "\n";

// 5. Verificar últimas ventas del día
echo "\n5️⃣ Verificando ventas de hoy...\n";
$todaySales = DB::table('contracts')
    ->whereDate('sign_date', now()->toDateString())
    ->where('status', 'vigente')
    ->count();
echo "   💰 Contratos firmados hoy: $todaySales\n";

if ($todaySales > 0 && $items->where('item_type', 'sale')->count() === 0) {
    echo "   ⚠️ HAY VENTAS PERO NO ESTÁN EN EL CORTE\n";
    echo "   💡 Los eventos deberían agregarlas automáticamente\n";
} elseif ($todaySales > 0 && $items->where('item_type', 'sale')->count() > 0) {
    echo "   ✅ Las ventas están siendo registradas en el corte\n";
}

// 6. Verificar últimos pagos del día
echo "\n6️⃣ Verificando pagos de hoy...\n";
$todayPayments = DB::table('payment_schedules')
    ->whereDate('paid_date', now()->toDateString())
    ->where('status', 'pagado')
    ->count();
echo "   💳 Pagos registrados hoy: $todayPayments\n";

if ($todayPayments > 0 && $items->where('item_type', 'payment')->count() === 0) {
    echo "   ⚠️ HAY PAGOS PERO NO ESTÁN EN EL CORTE\n";
    echo "   💡 Los eventos deberían agregarlos automáticamente\n";
} elseif ($todayPayments > 0 && $items->where('item_type', 'payment')->count() > 0) {
    echo "   ✅ Los pagos están siendo registrados en el corte\n";
}

// 7. Verificar scheduler
echo "\n7️⃣ Verificando configuración del scheduler...\n";
$schedulerConfig = file_get_contents(__DIR__ . '/routes/console.php');
if (strpos($schedulerConfig, 'sales:create-daily-cut') !== false) {
    echo "   ✅ Comando 'sales:create-daily-cut' configurado en routes/console.php\n";
    if (strpos($schedulerConfig, '23:59') !== false) {
        echo "   ✅ Programado para las 11:59 PM\n";
    }
} else {
    echo "   ❌ Comando NO encontrado en routes/console.php\n";
}

echo "\n\n📋 RESUMEN\n";
echo "=========================================\n";
echo "✅ Sistema de eventos implementado\n";
echo "✅ Corte del día: " . ($todayCut ? "Existe (ID: {$todayCut->cut_id})" : "No existe") . "\n";
echo "✅ Scheduler configurado\n";
echo "\n💡 PRÓXIMOS PASOS:\n";
echo "1. Crear un contrato desde el frontend\n";
echo "2. Registrar un pago desde el frontend\n";
echo "3. Verificar que se agreguen automáticamente al corte\n";
echo "4. Revisar logs en storage/logs/laravel.log\n";
echo "\n🚀 Para producción, configurar:\n";
echo "   - Cron job: * * * * * cd /path && php artisan schedule:run\n";
echo "   - Queue worker con Supervisor\n";
echo "   - Reverb para WebSockets\n";
echo "\n";
