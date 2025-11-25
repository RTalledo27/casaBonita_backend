<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\LogicwareContractImporter;
use Modules\Sales\Models\Contract;

echo "=== PRUEBA DE IMPORTACIÓN LOGICWARE ===\n\n";

try {
    // 1. Limpiar contratos existentes de Logicware
    echo "1. Limpiando contratos anteriores...\n";
    
    // Contar antes
    $countBefore = Contract::where('source', 'logicware')->count();
    echo "   Contratos encontrados: {$countBefore}\n";
    
    if ($countBefore > 0) {
        // Eliminar contratos y sus relaciones (cascade debería encargarse, pero por si acaso)
        // Nota: Usamos DB::table para ser más rápidos y evitar eventos si es necesario, 
        // pero Eloquent es mejor para asegurar integridad si hay observers
        Contract::where('source', 'logicware')->delete();
        echo "   ✅ Contratos eliminados correctamente.\n";
    } else {
        echo "   ℹ️ No había contratos para eliminar.\n";
    }
    echo "\n";

    // 2. Ejecutar importación
    echo "2. Ejecutando importación desde Logicware...\n";
    
    $importer = app(LogicwareContractImporter::class);
    
    // Importar últimos 30 días para asegurar que agarre datos recientes
    $startDate = now()->subDays(30)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');
    
    echo "   Buscando ventas desde {$startDate} hasta {$endDate}...\n";
    
    $result = $importer->importContracts($startDate, $endDate, true); // true = forzar refresh
    
    echo "\n";
    echo "   ✅ Importación finalizada!\n";
    echo "   📊 Resumen:\n";
    echo "      Procesados: " . ($result['processed'] ?? 0) . "\n";
    echo "      Creados: " . ($result['created'] ?? 0) . "\n";
    echo "      Errores: " . ($result['errors'] ?? 0) . "\n";
    
    // 3. Verificar resultados
    echo "\n3. Verificando datos importados...\n";
    
    $contracts = Contract::where('source', 'logicware')->with('paymentSchedules')->get();
    $countAfter = $contracts->count();
    
    echo "   Contratos en BD: {$countAfter}\n";
    
    if ($countAfter > 0) {
        $contract = $contracts->first();
        echo "   🔍 Inspeccionando primer contrato (ID: {$contract->contract_id}):\n";
        echo "      Cliente: " . ($contract->client->first_name ?? 'N/A') . " " . ($contract->client->last_name ?? '') . "\n";
        echo "      Cronogramas: " . $contract->paymentSchedules->count() . "\n";
        
        if ($contract->paymentSchedules->count() > 0) {
            echo "      ✅ Cronogramas generados correctamente!\n";
            $firstSchedule = $contract->paymentSchedules->first();
            echo "      Ejemplo de cuota: Tipo='{$firstSchedule->type}', Monto={$firstSchedule->amount}\n";
        } else {
            echo "      ⚠️ ALERTA: Contrato creado pero SIN cronogramas.\n";
        }
    } else {
        echo "      ⚠️ No se importaron contratos. Verifica si hay ventas en Logicware en este rango.\n";
    }

} catch (Exception $e) {
    echo "\n❌ ERROR CRÍTICO:\n";
    echo $e->getMessage() . "\n";
    echo "En: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString();
}
