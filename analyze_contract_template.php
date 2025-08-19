<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $spreadsheet = IOFactory::load('plantilla_importacion_contratos_simplificada.xlsx');
    $worksheet = $spreadsheet->getActiveSheet();
    $highestRow = $worksheet->getHighestRow();
    $highestCol = $worksheet->getHighestColumn();
    
    echo "=== ANÁLISIS DE PLANTILLA DE CONTRATOS SIMPLIFICADA ===\n";
    echo "Total de filas: " . $highestRow . "\n";
    echo "Última columna: " . $highestCol . "\n\n";
    
    // Mostrar encabezados
    echo "ENCABEZADOS COMPLETOS:\n";
    $headers = [];
    for ($col = 'A'; $col <= $highestCol; $col++) {
        $header = trim($worksheet->getCell($col . '1')->getValue());
        $headers[$col] = $header;
        echo $col . ": " . $header . "\n";
    }
    
    // Mostrar primera fila de datos para entender la estructura
    echo "\nPRIMERA FILA DE DATOS (Fila 2):\n";
    for ($col = 'A'; $col <= $highestCol; $col++) {
        $value = trim($worksheet->getCell($col . '2')->getValue());
        echo $col . ": " . $value . "\n";
    }
    
    echo "\n=== ANÁLISIS DE DATOS ===\n";
    
    $advisorContracts = [];
    $emptyStatusCount = 0;
    $validContracts = 0;
    $totalDataRows = 0;
    
    // Analizar datos desde la fila 2 (asumiendo que la fila 1 son encabezados)
    for ($row = 2; $row <= $highestRow; $row++) {
        $totalDataRows++;
        
        // Obtener datos de la fila según la estructura real
        $advisor = trim($worksheet->getCell('A' . $row)->getValue()); // ASESOR_NOMBRE en columna A
        $contractStatus = trim($worksheet->getCell('N' . $row)->getValue()); // ESTADO_CONTRATO en columna N
        $clientName = trim($worksheet->getCell('D' . $row)->getValue()); // CLIENTE_NOMBRES en columna D
        $lotNumber = trim($worksheet->getCell('I' . $row)->getValue()); // LOTE_NUMERO en columna I
        $manzana = trim($worksheet->getCell('J' . $row)->getValue()); // LOTE_MANZANA en columna J
        
        // Verificar si el estado del contrato está vacío
        if (empty($contractStatus)) {
            $emptyStatusCount++;
            echo "Fila $row - Contrato sin estado: Asesor='$advisor', Cliente='$clientName', Lote='$lotNumber$manzana'\n";
            continue;
        }
        
        // Contar contratos válidos por asesor
        if (!empty($advisor)) {
            if (!isset($advisorContracts[$advisor])) {
                $advisorContracts[$advisor] = 0;
            }
            $advisorContracts[$advisor]++;
            $validContracts++;
        }
    }
    
    echo "\n=== RESUMEN ESTADÍSTICO ===\n";
    echo "Total filas de datos: $totalDataRows\n";
    echo "Contratos con estado vacío: $emptyStatusCount\n";
    echo "Contratos válidos: $validContracts\n";
    echo "Porcentaje de contratos válidos: " . round(($validContracts / $totalDataRows) * 100, 2) . "%\n";
    
    echo "\n=== CONTRATOS POR ASESOR ===\n";
    arsort($advisorContracts); // Ordenar por cantidad de contratos descendente
    
    foreach ($advisorContracts as $advisor => $count) {
        echo "$advisor: $count contratos\n";
    }
    
    echo "\n=== TOTAL DE ASESORES ===\n";
    echo "Número de asesores únicos: " . count($advisorContracts) . "\n";
    
} catch (Exception $e) {
    echo "Error al procesar el archivo: " . $e->getMessage() . "\n";
}
?>