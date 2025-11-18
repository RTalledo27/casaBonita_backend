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
echo "🧪 TEST: Importación con Sincronización de Cronograma\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    // Limpiar datos previos
    echo "🧹 Limpiando contratos y cronogramas previos...\n";
    DB::table('payment_schedules')->whereIn('contract_id', function ($query) {
        $query->select('contract_id')
              ->from('contracts')
              ->where('source', 'logicware');
    })->delete();
    
    DB::table('contracts')->where('source', 'logicware')->delete();
    echo "✅ Datos limpiados\n\n";
    
    // Crear importador
    $logicwareService = new LogicwareApiService();
    $importer = new LogicwareContractImporter($logicwareService);
    
    // Importar contratos de noviembre 2025
    echo "📥 Importando contratos de noviembre 2025...\n\n";
    
    $result = $importer->importContracts(
        startDate: '2025-11-01',
        endDate: '2025-11-30',
        forceRefresh: false
    );
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📊 RESULTADO DE LA IMPORTACIÓN:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "Total de ventas procesadas: {$result['total_sales']}\n";
    echo "Contratos creados: {$result['contracts_created']}\n";
    echo "Contratos omitidos: {$result['contracts_skipped']}\n";
    echo "Errores: " . count($result['errors']) . "\n\n";
    
    if (!empty($result['warnings'])) {
        echo "⚠️  ADVERTENCIAS:\n";
        foreach (array_slice($result['warnings'], 0, 5) as $warning) {
            echo "  - {$warning}\n";
        }
        echo "\n";
    }
    
    if (!empty($result['errors'])) {
        echo "❌ ERRORES:\n";
        foreach (array_slice($result['errors'], 0, 5) as $error) {
            echo "  - {$error}\n";
        }
        echo "\n";
    }
    
    // Analizar un contrato con cronograma
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔍 ANÁLISIS DETALLADO DE CRONOGRAMAS:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $contracts = DB::table('contracts')
        ->where('source', 'logicware')
        ->limit(3)
        ->get();
    
    foreach ($contracts as $contract) {
        echo "📄 Contrato: {$contract->contract_number}\n";
        
        $client = DB::table('clients')->where('client_id', $contract->client_id)->first();
        $clientName = $client ? ($client->name ?? 'Sin nombre') : 'Cliente no encontrado';
        
        $lot = DB::table('lots')->where('lot_id', $contract->lot_id)->first();
        $lotCode = $lot ? ($lot->property_code ?? 'Sin código') : 'Lote no encontrado';
        
        echo "   Cliente: {$clientName}\n";
        echo "   Lote: {$lotCode}\n\n";
        
        $schedules = DB::table('payment_schedules')
            ->where('contract_id', $contract->contract_id)
            ->orderBy('installment_number')
            ->get();
        
        $totalCuotas = $schedules->count();
        $cuotasPagadas = $schedules->where('status', 'pagado')->count();
        $cuotasPendientes = $totalCuotas - $cuotasPagadas;
        
        // Agrupar por tipo
        $porTipo = $schedules->groupBy('type')->map(fn($g) => $g->count())->toArray();
        
        echo "   📊 Total cuotas: {$totalCuotas}\n";
        echo "   ✅ Pagadas: {$cuotasPagadas}\n";
        echo "   ⏳ Pendientes: {$cuotasPendientes}\n\n";
        
        echo "   📋 Por tipo:\n";
        foreach ($porTipo as $tipo => $cantidad) {
            $tipoNombre = [
                'inicial' => 'Cuotas Iniciales',
                'financiamiento' => 'Cuotas de Financiamiento',
                'balon' => '🎈 Cuota Balón',
                'bono_bpp' => '🎁 Bono Buen Pagador',
                'otro' => 'Otras'
            ][$tipo] ?? $tipo;
            echo "      - {$tipoNombre}: {$cantidad}\n";
        }
        
        // Verificar si tiene cuota balón o BPP
        $tieneBalon = $schedules->where('type', 'balon')->count() > 0;
        $tieneBPP = $schedules->where('type', 'bono_bpp')->count() > 0;
        
        if ($tieneBalon || $tieneBPP) {
            echo "\n   🎉 CUOTAS ESPECIALES DETECTADAS:\n";
            if ($tieneBalon) {
                $balon = $schedules->where('type', 'balon')->first();
                echo "      🎈 Cuota Balón: S/ " . number_format($balon->amount, 2) . " (Vence: {$balon->due_date})\n";
            }
            if ($tieneBPP) {
                $bpp = $schedules->where('type', 'bono_bpp')->first();
                echo "      🎁 Bono BPP: S/ " . number_format($bpp->amount, 2) . " (Vence: {$bpp->due_date})\n";
            }
        }
        
        echo "\n" . str_repeat("─", 60) . "\n\n";
    }
    
    // Resumen global
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🎯 RESUMEN GLOBAL:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $totalContracts = DB::table('contracts')->where('source', 'logicware')->count();
    $totalSchedules = DB::table('payment_schedules')
        ->whereIn('contract_id', function ($query) {
            $query->select('contract_id')->from('contracts')->where('source', 'logicware');
        })->count();
    $totalPagadas = DB::table('payment_schedules')
        ->where('status', 'pagado')
        ->whereIn('contract_id', function ($query) {
            $query->select('contract_id')->from('contracts')->where('source', 'logicware');
        })->count();
    
    $contratosConBalon = DB::table('payment_schedules')
        ->where('type', 'balon')
        ->whereIn('contract_id', function ($query) {
            $query->select('contract_id')->from('contracts')->where('source', 'logicware');
        })
        ->distinct('contract_id')
        ->count('contract_id');
    
    $contratosConBPP = DB::table('payment_schedules')
        ->where('type', 'bono_bpp')
        ->whereIn('contract_id', function ($query) {
            $query->select('contract_id')->from('contracts')->where('source', 'logicware');
        })
        ->distinct('contract_id')
        ->count('contract_id');
    
    echo "📊 Contratos importados: {$totalContracts}\n";
    echo "📅 Total de cuotas: {$totalSchedules}\n";
    echo "✅ Cuotas pagadas: {$totalPagadas}\n";
    echo "🎈 Contratos con Cuota Balón: {$contratosConBalon}\n";
    echo "🎁 Contratos con Bono BPP: {$contratosConBPP}\n\n";
    
    echo "✅ ¡Importación completada con éxito!\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
