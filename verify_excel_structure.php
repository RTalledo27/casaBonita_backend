<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

try {
    $reader = IOFactory::createReader('Xlsx');
    $spreadsheet = $reader->load('template_test.xlsx');
    $worksheet = $spreadsheet->getActiveSheet();
    
    $highestColumn = $worksheet->getHighestColumn();
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
    
    echo "=== ESTRUCTURA DEL ARCHIVO EXCEL ===\n";
    echo "Total columnas: " . $highestColumnIndex . "\n";
    echo "Última columna: " . $highestColumn . "\n\n";
    
    echo "=== HEADERS (FILA 1) ===\n";
    for($col = 1; $col <= $highestColumnIndex; $col++) {
        $header = $worksheet->getCell([$col, 1])->getValue();
        $columnLetter = Coordinate::stringFromColumnIndex($col);
        echo "Col {$col} ({$columnLetter}): [{$header}]\n";
    }
    
    echo "\n=== VALORES FILA 2 (FINANCIAMIENTO) ===\n";
    for($col = 1; $col <= $highestColumnIndex; $col++) {
        $value = $worksheet->getCell([$col, 2])->getValue();
        $columnLetter = Coordinate::stringFromColumnIndex($col);
        echo "Col {$col} ({$columnLetter}): [{$value}]\n";
    }
    
    // Buscar específicamente la columna J
    echo "\n=== BÚSQUEDA ESPECÍFICA DE COLUMNA J ===\n";
    $foundJ = false;
    for($col = 1; $col <= $highestColumnIndex; $col++) {
        $header = $worksheet->getCell([$col, 1])->getValue();
        if (trim($header) === 'J') {
            $foundJ = true;
            $value = $worksheet->getCell([$col, 2])->getValue();
            $columnLetter = Coordinate::stringFromColumnIndex($col);
            echo "🎯 COLUMNA J ENCONTRADA:\n";
            echo "  - Posición: Col {$col} ({$columnLetter})\n";
            echo "  - Header: [{$header}]\n";
            echo "  - Valor fila 2: [{$value}]\n";
            break;
        }
    }
    
    if (!$foundJ) {
        echo "❌ COLUMNA J NO ENCONTRADA\n";
        echo "Verificando si hay columnas con headers similares a 'J':\n";
        for($col = 1; $col <= $highestColumnIndex; $col++) {
            $header = $worksheet->getCell([$col, 1])->getValue();
            if (strlen(trim($header)) === 1 && ctype_alpha($header)) {
                $columnLetter = Coordinate::stringFromColumnIndex($col);
                echo "  - Col {$col} ({$columnLetter}): [{$header}]\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}