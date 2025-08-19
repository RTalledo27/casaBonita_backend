<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Sales\app\Services\ContractImportService;
use Illuminate\Support\Facades\Log;

echo "=== PRUEBA DE IMPORTACIÓN DE CONTRATOS CON CAMPOS CORREGIDOS ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $service = new ContractImportService();
    
    $filePath = 'storage/app/public/imports/contratos_prueba.xlsx';
    
    if (!file_exists($filePath)) {
        echo "❌ ERROR: Archivo no encontrado: $filePath\n";
        exit(1);
    }
    
    echo "📁 Archivo encontrado: $filePath\n";
    echo "📊 Iniciando procesamiento...\n\n";
    
    $result = $service->processExcelSimplified($filePath);
    
    echo "✅ RESULTADO DEL PROCESAMIENTO:\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // Verificar si se crearon contratos
    $contractsCount = \DB::table('contracts')->count();
    echo "📋 Total de contratos en la base de datos: $contractsCount\n";
    
    // Mostrar los últimos contratos creados
    $latestContracts = \DB::table('contracts')
        ->orderBy('contract_id', 'desc')
        ->limit(3)
        ->get(['contract_id', 'contract_number', 'total_price', 'down_payment', 'financing_amount']);
    
    if ($latestContracts->count() > 0) {
        echo "\n📋 ÚLTIMOS CONTRATOS CREADOS:\n";
        foreach ($latestContracts as $contract) {
            echo "- ID: {$contract->contract_id}, Número: {$contract->contract_number}, ";
            echo "Total: {$contract->total_price}, Enganche: {$contract->down_payment}, ";
            echo "Financiado: {$contract->financing_amount}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . "\n";
    echo "📍 Línea: " . $e->getLine() . "\n";
    echo "\n🔍 STACK TRACE:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";