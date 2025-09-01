<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Sales\app\Services\ContractImportService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;
use ReflectionClass;

echo "=== DEBUG ANÁLISIS DE IMPORTACIÓN ===\n";
echo "Analizando por qué solo 6 filas se procesan exitosamente...\n\n";

// Cargar el archivo Excel
$filePath = $argv[1] ?? null;
if (!$filePath) {
    echo "USO: php debug_import_issues.php <ruta_archivo_excel>\n";
    echo "Ejemplo: php debug_import_issues.php C:\\path\\to\\contracts.xlsx\n";
    exit(1);
}

if (!file_exists($filePath)) {
    echo "ERROR: Archivo no encontrado: $filePath\n";
    exit(1);
}

try {
    $spreadsheet = IOFactory::load($filePath);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
    
    if (empty($rows)) {
        echo "ERROR: El archivo está vacío\n";
        exit(1);
    }
    
    $headers = array_shift($rows);
    echo "Headers encontrados: " . implode(', ', $headers) . "\n";
    echo "Total de filas de datos: " . count($rows) . "\n\n";
    
    // Crear instancia del servicio
    $contractImportService = new ContractImportService();
    $reflection = new ReflectionClass($contractImportService);
    
    // Validar estructura del Excel
    echo "=== VALIDACIÓN DE ESTRUCTURA ===\n";
    $validateMethod = $reflection->getMethod('validateExcelStructureSimplified');
    $validateMethod->setAccessible(true);
    $validation = $validateMethod->invoke($contractImportService, $headers);
    
    if (!$validation['valid']) {
        echo "ERROR: Estructura inválida - " . $validation['error'] . "\n";
        exit(1);
    }
    echo "✓ Estructura del Excel válida\n\n";
    
    // Contadores para análisis
    $successCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    $emptyRows = 0;
    $validationErrors = 0;
    $processingErrors = 0;
    
    $errorDetails = [];
    $skippedDetails = [];
    
    echo "=== ANÁLISIS FILA POR FILA ===\n";
    
    foreach ($rows as $index => $row) {
        $rowNumber = $index + 2;
        echo "\n--- FILA $rowNumber ---\n";
        
        // Verificar si la fila está vacía
        $mapRowMethod = $reflection->getMethod('mapRowDataSimplified');
        $mapRowMethod->setAccessible(true);
        $mappedData = $mapRowMethod->invoke($contractImportService, $row, $headers);
        
        $isEmptyMethod = $reflection->getMethod('isEmptyRowSimplified');
        $isEmptyMethod->setAccessible(true);
        $isEmpty = $isEmptyMethod->invoke($contractImportService, $mappedData);
        
        if ($isEmpty) {
            echo "FILA VACÍA - Omitida\n";
            $emptyRows++;
            $skippedCount++;
            continue;
        }
        
        // Mostrar datos clave
        echo "Cliente: " . ($mappedData['cliente_nombres'] ?? 'N/A') . "\n";
        echo "Lote: " . ($mappedData['lot_number'] ?? 'N/A') . " Manzana: " . ($mappedData['lot_manzana'] ?? 'N/A') . "\n";
        echo "Tipo Operación: '" . ($mappedData['operation_type'] ?? 'N/A') . "'\n";
        echo "Estado Contrato: '" . ($mappedData['contract_status'] ?? 'N/A') . "'\n";
        
        // Validar datos de la fila
        $validateRowMethod = $reflection->getMethod('validateRowDataSimplified');
        $validateRowMethod->setAccessible(true);
        $rowValidation = $validateRowMethod->invoke($contractImportService, $mappedData);
        
        if (!$rowValidation['valid']) {
            echo "ERROR DE VALIDACIÓN: " . $rowValidation['message'] . "\n";
            $validationErrors++;
            $errorCount++;
            $errorDetails[] = [
                'row' => $rowNumber,
                'type' => 'validation',
                'error' => $rowValidation['message'],
                'data' => $mappedData
            ];
            continue;
        }
        
        // Verificar si debe crear contrato
        $shouldCreateMethod = $reflection->getMethod('shouldCreateContractSimplified');
        $shouldCreateMethod->setAccessible(true);
        $shouldCreate = $shouldCreateMethod->invoke($contractImportService, $mappedData);
        
        echo "¿Debe crear contrato? " . ($shouldCreate ? 'SÍ' : 'NO') . "\n";
        
        // Intentar procesar la fila completa
        try {
            $processRowMethod = $reflection->getMethod('processRowSimplified');
            $processRowMethod->setAccessible(true);
            $result = $processRowMethod->invoke($contractImportService, $row, $headers);
            
            echo "Resultado: " . $result['status'] . " - " . $result['message'] . "\n";
            
            if ($result['status'] === 'success') {
                $successCount++;
            } elseif ($result['status'] === 'skipped') {
                $skippedCount++;
                $skippedDetails[] = [
                    'row' => $rowNumber,
                    'reason' => $result['message'],
                    'data' => $mappedData
                ];
            } else {
                $errorCount++;
                $processingErrors++;
                $errorDetails[] = [
                    'row' => $rowNumber,
                    'type' => 'processing',
                    'error' => $result['message'],
                    'data' => $mappedData
                ];
            }
            
        } catch (Exception $e) {
            echo "EXCEPCIÓN: " . $e->getMessage() . "\n";
            $errorCount++;
            $processingErrors++;
            $errorDetails[] = [
                'row' => $rowNumber,
                'type' => 'exception',
                'error' => $e->getMessage(),
                'data' => $mappedData
            ];
        }
    }
    
    echo "\n\n=== RESUMEN FINAL ===\n";
    echo "Total de filas procesadas: " . count($rows) . "\n";
    echo "Filas exitosas: $successCount\n";
    echo "Filas con errores: $errorCount\n";
    echo "Filas omitidas: $skippedCount\n";
    echo "\nDesglose de problemas:\n";
    echo "- Filas vacías: $emptyRows\n";
    echo "- Errores de validación: $validationErrors\n";
    echo "- Errores de procesamiento: $processingErrors\n";
    
    echo "\n=== DETALLES DE ERRORES MÁS COMUNES ===\n";
    $errorTypes = [];
    foreach ($errorDetails as $error) {
        $errorMsg = $error['error'];
        if (!isset($errorTypes[$errorMsg])) {
            $errorTypes[$errorMsg] = 0;
        }
        $errorTypes[$errorMsg]++;
    }
    
    arsort($errorTypes);
    foreach ($errorTypes as $errorMsg => $count) {
        echo "- \"$errorMsg\": $count veces\n";
    }
    
    echo "\n=== DETALLES DE FILAS OMITIDAS ===\n";
    $skipReasons = [];
    foreach ($skippedDetails as $skip) {
        $reason = $skip['reason'];
        if (!isset($skipReasons[$reason])) {
            $skipReasons[$reason] = 0;
        }
        $skipReasons[$reason]++;
    }
    
    arsort($skipReasons);
    foreach ($skipReasons as $reason => $count) {
        echo "- \"$reason\": $count veces\n";
    }
    
} catch (Exception $e) {
    echo "ERROR FATAL: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}