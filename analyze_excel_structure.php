<?php

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

try {
    // Cargar el archivo Excel
    $inputFileName = 'storage/app/public/imports/contratos_prueba_real.xlsx';
    
    if (!file_exists($inputFileName)) {
        echo "Error: El archivo $inputFileName no existe.\n";
        exit(1);
    }
    
    $spreadsheet = IOFactory::load($inputFileName);
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Obtener el rango de datos
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    
    echo "=== ANÁLISIS DE ESTRUCTURA DEL EXCEL ===\n";
    echo "Archivo: $inputFileName\n";
    echo "Filas totales: $highestRow\n";
    echo "Columnas: $highestColumn\n\n";
    
    // Leer encabezados (primera fila)
    echo "=== ENCABEZADOS ===\n";
    $headers = [];
    for ($col = 'A'; $col <= $highestColumn; $col++) {
        $cellValue = $worksheet->getCell($col . '1')->getValue();
        $headers[$col] = $cellValue;
        echo "$col: $cellValue\n";
    }
    echo "\n";
    
    // Analizar estructura de datos y buscar filas combinadas
    echo "=== ANÁLISIS DE FILAS COMBINADAS ===\n";
    $contractData = [];
    $duplicateRows = [];
    $emptyStatusCount = 0;
    $validContracts = 0;
    
    // Buscar columnas importantes
    $advisorCol = null;
    $contractStatusCol = null;
    $contractNumberCol = null;
    
    foreach ($headers as $col => $header) {
        if (stripos($header, 'asesor') !== false || stripos($header, 'vendedor') !== false) {
            $advisorCol = $col;
        }
        if (stripos($header, 'estado') !== false && stripos($header, 'contrato') !== false) {
            $contractStatusCol = $col;
        }
        if (stripos($header, 'contrato') !== false && stripos($header, 'numero') !== false) {
            $contractNumberCol = $col;
        }
    }
    
    echo "Columna Asesor: $advisorCol\n";
    echo "Columna Estado Contrato: $contractStatusCol\n";
    echo "Columna Número Contrato: $contractNumberCol\n\n";
    
    // Analizar cada fila de datos
    for ($row = 2; $row <= $highestRow; $row++) {
        $advisor = $advisorCol ? trim($worksheet->getCell($advisorCol . $row)->getValue()) : '';
        $contractStatus = $contractStatusCol ? trim($worksheet->getCell($contractStatusCol . $row)->getValue()) : '';
        $contractNumber = $contractNumberCol ? trim($worksheet->getCell($contractNumberCol . $row)->getValue()) : '';
        
        // Verificar si es una fila vacía o de continuación
        $isEmpty = empty($advisor) && empty($contractStatus) && empty($contractNumber);
        
        if (!$isEmpty) {
            // Verificar estado del contrato
            if (empty($contractStatus)) {
                $emptyStatusCount++;
            } else {
                $validContracts++;
            }
            
            // Agrupar por asesor
            if (!empty($advisor)) {
                if (!isset($contractData[$advisor])) {
                    $contractData[$advisor] = [
                        'rows' => [],
                        'contracts_with_status' => 0,
                        'contracts_without_status' => 0,
                        'unique_contract_numbers' => []
                    ];
                }
                
                $contractData[$advisor]['rows'][] = $row;
                
                if (!empty($contractNumber)) {
                    $contractData[$advisor]['unique_contract_numbers'][] = $contractNumber;
                }
                
                if (empty($contractStatus)) {
                    $contractData[$advisor]['contracts_without_status']++;
                } else {
                    $contractData[$advisor]['contracts_with_status']++;
                }
            }
        }
    }
    
    echo "=== RESUMEN GENERAL ===\n";
    echo "Total filas de datos: " . ($highestRow - 1) . "\n";
    echo "Contratos con estado válido: $validContracts\n";
    echo "Contratos con estado vacío: $emptyStatusCount\n";
    $totalContracts = $validContracts + $emptyStatusCount;
    if ($totalContracts > 0) {
        echo "Porcentaje válido: " . round(($validContracts / $totalContracts) * 100, 2) . "%\n\n";
    } else {
        echo "Porcentaje válido: 0% (no hay datos de contratos)\n\n";
    }
    
    echo "=== ANÁLISIS POR ASESOR ===\n";
    foreach ($contractData as $advisor => $data) {
        $uniqueContracts = count(array_unique($data['unique_contract_numbers']));
        $totalRows = count($data['rows']);
        
        echo "ASESOR: $advisor\n";
        echo "  - Filas ocupadas: $totalRows\n";
        echo "  - Contratos únicos: $uniqueContracts\n";
        echo "  - Con estado: {$data['contracts_with_status']}\n";
        echo "  - Sin estado: {$data['contracts_without_status']}\n";
        
        if ($totalRows > $uniqueContracts) {
            echo "  - ⚠️  POSIBLE DUPLICACIÓN: $totalRows filas para $uniqueContracts contratos\n";
            $duplicateRows[$advisor] = [
                'rows' => $totalRows,
                'unique_contracts' => $uniqueContracts,
                'difference' => $totalRows - $uniqueContracts
            ];
        }
        
        // Mostrar filas específicas para LUIS TAVARA
        if (stripos($advisor, 'LUIS') !== false && stripos($advisor, 'TAVARA') !== false) {
            echo "  - Filas específicas: " . implode(', ', $data['rows']) . "\n";
            echo "  - Números de contrato: " . implode(', ', array_unique($data['unique_contract_numbers'])) . "\n";
        }
        
        echo "\n";
    }
    
    if (!empty($duplicateRows)) {
        echo "=== RESUMEN DE DUPLICACIONES DETECTADAS ===\n";
        foreach ($duplicateRows as $advisor => $info) {
            echo "$advisor: {$info['rows']} filas → {$info['unique_contracts']} contratos únicos (diferencia: {$info['difference']})\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error al procesar el archivo: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}