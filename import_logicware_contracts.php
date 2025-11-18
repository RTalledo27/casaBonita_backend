<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\LogicwareContractImporter;
use App\Services\LogicwareApiService;
use Illuminate\Support\Facades\DB;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📥 IMPORTACIÓN DE CONTRATOS DESDE LOGICWARE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    // Crear servicios
    $logicwareService = new LogicwareApiService();
    $importer = new LogicwareContractImporter($logicwareService);
    
    // Importar contratos de noviembre 2025
    echo "📅 Importando contratos de Noviembre 2025...\n";
    echo "🔄 Esto puede tomar unos minutos...\n\n";
    
    $startTime = microtime(true);
    
    $result = $importer->importContracts(
        startDate: '2025-11-01',
        endDate: '2025-11-30',
        forceRefresh: false
    );
    
    $duration = round(microtime(true) - $startTime, 2);
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ IMPORTACIÓN COMPLETADA\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "⏱️  Tiempo: {$duration} segundos\n";
    echo "📊 Total de ventas procesadas: {$result['total_sales']}\n";
    echo "✅ Contratos creados: {$result['contracts_created']}\n";
    echo "⏭️  Contratos omitidos: {$result['contracts_skipped']}\n";
    echo "❌ Errores: " . count($result['errors']) . "\n\n";
    
    if (!empty($result['errors'])) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "❌ ERRORES ENCONTRADOS:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        foreach (array_slice($result['errors'], 0, 10) as $error) {
            echo "  • {$error}\n";
        }
        if (count($result['errors']) > 10) {
            $remaining = count($result['errors']) - 10;
            echo "  ... y {$remaining} errores más\n";
        }
        echo "\n";
    }
    
    // Mostrar estadísticas de cronogramas
    $totalSchedules = DB::table('payment_schedules')
        ->whereIn('contract_id', function ($query) {
            $query->select('contract_id')->from('contracts')->where('source', 'logicware');
        })->count();
    
    $paidSchedules = DB::table('payment_schedules')
        ->where('status', 'pagado')
        ->whereIn('contract_id', function ($query) {
            $query->select('contract_id')->from('contracts')->where('source', 'logicware');
        })->count();
    
    $balloonCount = DB::table('payment_schedules')
        ->where('type', 'balon')
        ->whereIn('contract_id', function ($query) {
            $query->select('contract_id')->from('contracts')->where('source', 'logicware');
        })->count();
    
    $bppCount = DB::table('payment_schedules')
        ->where('type', 'bono_bpp')
        ->whereIn('contract_id', function ($query) {
            $query->select('contract_id')->from('contracts')->where('source', 'logicware');
        })->count();
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📅 CRONOGRAMAS DE PAGO:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "📋 Total de cuotas creadas: {$totalSchedules}\n";
    echo "✅ Cuotas pagadas: {$paidSchedules}\n";
    echo "🎈 Cuotas balón: {$balloonCount}\n";
    echo "🎁 Cuotas BPP: {$bppCount}\n\n";
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🎉 ¡Proceso completado con éxito!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
} catch (Exception $e) {
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "❌ ERROR FATAL:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo $e->getMessage() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
