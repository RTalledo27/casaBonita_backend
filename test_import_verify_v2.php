<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\LogicwareContractImporter;
use Modules\Sales\Models\Contract;
use Illuminate\Support\Facades\Log;

$logFile = 'import_summary.txt';
file_put_contents($logFile, "INICIO DE PRUEBA\n");

try {
    // 1. Limpiar
    $deleted = Contract::where('source', 'logicware')->delete();
    file_put_contents($logFile, "Contratos eliminados: {$deleted}\n", FILE_APPEND);

    // 2. Importar
    $importer = app(LogicwareContractImporter::class);
    // Ampliar rango de fechas a 60 días por si acaso
    $startDate = now()->subDays(60)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');
    
    file_put_contents($logFile, "Importando desde {$startDate} hasta {$endDate}...\n", FILE_APPEND);
    
    $result = $importer->importContracts($startDate, $endDate, true);
    
    file_put_contents($logFile, "RESULTADO IMPORTACION:\n", FILE_APPEND);
    file_put_contents($logFile, "Procesados: " . ($result['processed'] ?? 0) . "\n", FILE_APPEND);
    file_put_contents($logFile, "Creados: " . ($result['created'] ?? 0) . "\n", FILE_APPEND);
    file_put_contents($logFile, "Errores: " . json_encode($result['errors'] ?? []) . "\n", FILE_APPEND);
    
    // 3. Verificar
    $contracts = Contract::where('source', 'logicware')->get();
    file_put_contents($logFile, "Contratos en BD: " . $contracts->count() . "\n", FILE_APPEND);
    
    if ($contracts->count() > 0) {
        $c = $contracts->first();
        $schedules = $c->paymentSchedules()->count();
        file_put_contents($logFile, "Primer contrato ID: {$c->contract_id}\n", FILE_APPEND);
        file_put_contents($logFile, "Cronogramas: {$schedules}\n", FILE_APPEND);
        
        if ($schedules > 0) {
            $first = $c->paymentSchedules()->first();
            file_put_contents($logFile, "Tipo primera cuota: {$first->type}\n", FILE_APPEND);
            file_put_contents($logFile, "✅ EXITO: Cronogramas creados con tipo correcto.\n", FILE_APPEND);
        } else {
            file_put_contents($logFile, "❌ FALLO: Contrato sin cronogramas.\n", FILE_APPEND);
        }
    } else {
        file_put_contents($logFile, "⚠️ ALERTA: No se importaron contratos (¿quizás no hay ventas en Logicware?).\n", FILE_APPEND);
    }

} catch (Exception $e) {
    file_put_contents($logFile, "❌ ERROR FATAL: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents($logFile, $e->getTraceAsString(), FILE_APPEND);
}
