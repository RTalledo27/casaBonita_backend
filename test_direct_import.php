<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

use Modules\Sales\app\Services\ContractImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

try {
    echo "=== PRUEBA DIRECTA DE IMPORTACIÓN ===\n\n";
    
    // Ruta del archivo Excel generado
    $filePath = '../test_contracts_template_simplified.xlsx';
    
    if (!file_exists($filePath)) {
        echo "❌ Archivo no encontrado: $filePath\n";
        exit(1);
    }
    
    echo "📁 Archivo encontrado: $filePath\n";
    echo "📊 Tamaño: " . filesize($filePath) . " bytes\n\n";
    
    // Crear instancia del servicio
    $importService = new ContractImportService();
    
    // Simular UploadedFile
    $uploadedFile = new UploadedFile(
        $filePath,
        'test_contracts_template_simplified.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true // test mode
    );
    
    echo "🔄 Iniciando importación...\n\n";
    
    // Ejecutar importación
    $result = $importService->processExcelSimplified($uploadedFile, 1); // user_id = 1
    
    echo "✅ Importación completada\n\n";
    echo "📋 RESULTADOS:\n";
    echo "- Éxitos: " . $result['success_count'] . "\n";
    echo "- Errores: " . $result['error_count'] . "\n";
    echo "- Total procesados: " . $result['total_processed'] . "\n\n";
    
    if (!empty($result['errors'])) {
        echo "❌ ERRORES ENCONTRADOS:\n";
        foreach ($result['errors'] as $error) {
            echo "  - $error\n";
        }
        echo "\n";
    }
    
    if (!empty($result['processed'])) {
        echo "✅ REGISTROS PROCESADOS:\n";
        foreach ($result['processed'] as $processed) {
            echo "  - $processed\n";
        }
        echo "\n";
    }
    
    // Verificar logs recientes
    echo "📝 Verificando logs recientes...\n";
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        $recentLogs = array_slice(explode("\n", $logs), -20);
        
        foreach ($recentLogs as $log) {
            if (strpos($log, 'shouldCreateContractSimplified') !== false) {
                echo "  📄 $log\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error durante la importación: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}