<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test the import service
try {
    $service = new \Modules\Sales\app\Services\ContractImportService();
    $result = $service->processExcelSimplified('storage/app/test_contracts_simplified.xlsx');
    
    echo "Resultado de la importación:\n";
    echo "Procesados: " . count($result['processed']) . "\n";
    echo "Errores: " . count($result['errors']) . "\n";
    
    if (!empty($result['errors'])) {
        echo "\nErrores encontrados:\n";
        foreach ($result['errors'] as $error) {
            echo "- Fila {$error['row']}: {$error['message']}\n";
        }
    }
    
    if (!empty($result['processed'])) {
        echo "\nProcesados exitosamente:\n";
        foreach ($result['processed'] as $processed) {
            echo "- Fila {$processed['row']}: {$processed['client']} - Lote {$processed['lot']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}