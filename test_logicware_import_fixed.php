<?php

/**
 * TEST: Importación de Contratos desde Logicware (CORREGIDO)
 * 
 * Verifica que:
 * 1. Los asesores se vinculan correctamente con score-based matching
 * 2. Los cronogramas se generan automáticamente
 * 3. Las fechas de los cronogramas son correctas (desde saleStartDate)
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\LogicwareContractImporter;
use Modules\Sales\Models\Contract;
use Modules\Collections\Models\PaymentSchedule;
use Carbon\Carbon;

echo "=== TEST IMPORTACIÓN LOGICWARE (CORREGIDA) ===\n\n";

try {
    $importer = app(LogicwareContractImporter::class);
    
    // IMPORTANTE: Usar fechas recientes donde hay ventas
    $startDate = '2025-11-15';
    $endDate = '2025-11-17';
    
    echo "📅 Rango de fechas: {$startDate} a {$endDate}\n\n";
    
    echo "1️⃣ Obteniendo ventas desde Logicware...\n";
    
    // Importar contratos (sin forzar refresh para no consumir API)
    $result = $importer->importContracts($startDate, $endDate, false);
    
    echo "\n✅ IMPORTACIÓN COMPLETADA\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Total ventas procesadas: {$result['total_sales']}\n";
    echo "Contratos creados: {$result['contracts_created']}\n";
    echo "Contratos omitidos: {$result['contracts_skipped']}\n";
    echo "Errores: " . count($result['errors']) . "\n";
    echo "Advertencias: " . count($result['warnings']) . "\n\n";
    
    if (!empty($result['errors'])) {
        echo "❌ ERRORES:\n";
        foreach ($result['errors'] as $error) {
            echo "  • {$error['document_number']}: {$error['error']}\n";
        }
        echo "\n";
    }
    
    if (!empty($result['warnings'])) {
        echo "⚠️ ADVERTENCIAS:\n";
        foreach (array_slice($result['warnings'], 0, 5) as $warning) {
            echo "  • {$warning}\n";
        }
        if (count($result['warnings']) > 5) {
            echo "  ... y " . (count($result['warnings']) - 5) . " más\n";
        }
        echo "\n";
    }
    
    // Verificar últimos contratos creados
    if ($result['contracts_created'] > 0) {
        echo "2️⃣ Verificando contratos recién creados...\n\n";
        
        $recentContracts = Contract::with(['client', 'lot', 'advisor.user'])
            ->orderBy('created_at', 'desc')
            ->limit($result['contracts_created'])
            ->get();
        
        foreach ($recentContracts as $index => $contract) {
            $contractNum = $index + 1;
            echo "📋 CONTRATO #{$contractNum}\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "ID: {$contract->contract_id}\n";
            echo "Número: {$contract->contract_number}\n";
            echo "Cliente: {$contract->client->first_name} {$contract->client->last_name}\n";
            $lotCode = $contract->lot->external_code ?? $contract->lot->num_lot;
            echo "Lote: {$lotCode}\n";
            
            // VERIFICACIÓN 1: Asesor vinculado
            if ($contract->advisor) {
                echo "✅ Asesor: {$contract->advisor->user->first_name} {$contract->advisor->user->last_name}\n";
            } else {
                echo "❌ Asesor: NO VINCULADO\n";
            }
            
            // VERIFICACIÓN 2: Fechas correctas
            echo "📅 Fecha contrato: {$contract->contract_date}\n";
            echo "📅 Fecha firma: {$contract->sign_date}\n";
            
            // VERIFICACIÓN 3: Cronogramas generados
            $schedules = PaymentSchedule::where('contract_id', $contract->contract_id)
                ->orderBy('due_date')
                ->get();
            
            if ($schedules->count() > 0) {
                echo "✅ Cronogramas: {$schedules->count()} cuotas generadas\n";
                
                $firstSchedule = $schedules->first();
                $lastSchedule = $schedules->last();
                
                echo "   • Primera cuota: {$firstSchedule->due_date} - S/ " . number_format($firstSchedule->amount, 2) . "\n";
                echo "   • Última cuota: {$lastSchedule->due_date} - S/ " . number_format($lastSchedule->amount, 2) . "\n";
                
                // Verificar que la primera cuota sea después de la fecha de venta
                $contractDate = Carbon::parse($contract->contract_date);
                $firstDueDate = Carbon::parse($firstSchedule->due_date);
                
                if ($firstDueDate->gte($contractDate)) {
                    echo "   ✅ Fechas correctas: Primera cuota es posterior a fecha de venta\n";
                } else {
                    echo "   ⚠️ Fechas incorrectas: Primera cuota ({$firstSchedule->due_date}) es anterior a fecha de venta ({$contract->contract_date})\n";
                }
                
            } else {
                echo "❌ Cronogramas: NO GENERADOS\n";
            }
            
            echo "\n";
        }
        
        // Resumen de verificación
        echo "3️⃣ RESUMEN DE VERIFICACIÓN\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        $withAdvisor = $recentContracts->filter(fn($c) => $c->advisor_id !== null)->count();
        $withSchedules = $recentContracts->filter(function($c) {
            return PaymentSchedule::where('contract_id', $c->contract_id)->exists();
        })->count();
        
        $advisorPercentage = ($withAdvisor / $recentContracts->count()) * 100;
        $schedulesPercentage = ($withSchedules / $recentContracts->count()) * 100;
        
        echo "✅ Contratos con asesor: {$withAdvisor}/{$recentContracts->count()} (" . round($advisorPercentage, 1) . "%)\n";
        echo "✅ Contratos con cronogramas: {$withSchedules}/{$recentContracts->count()} (" . round($schedulesPercentage, 1) . "%)\n\n";
        
        if ($advisorPercentage >= 80 && $schedulesPercentage >= 80) {
            echo "🎉 IMPORTACIÓN EXITOSA: Más del 80% de contratos tienen asesor y cronogramas\n";
        } elseif ($advisorPercentage < 50) {
            echo "⚠️ ADVERTENCIA: Menos del 50% de contratos tienen asesor vinculado\n";
        } elseif ($schedulesPercentage < 50) {
            echo "⚠️ ADVERTENCIA: Menos del 50% de contratos tienen cronogramas generados\n";
        }
    }
    
} catch (Exception $e) {
    echo "\n❌ ERROR EN IMPORTACIÓN:\n";
    echo "Mensaje: {$e->getMessage()}\n";
    echo "Archivo: {$e->getFile()}:{$e->getLine()}\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString();
}

echo "\n=== FIN TEST ===\n";
