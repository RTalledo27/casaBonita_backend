<?php

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;

// Configurar logging para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== DIAGNÓSTICO COMPLETO DE MANZANA J ===\n\n";

// Buscar archivos Excel en el proyecto
$possiblePaths = [
    'template_test.xlsx',
    'storage/app/template_test.xlsx',
    'storage/template_test.xlsx',
    'public/template_test.xlsx'
];

$excelFile = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $excelFile = $path;
        echo "📁 Archivo Excel encontrado: {$path}\n";
        break;
    }
}

if (!$excelFile) {
    echo "⚠️  No se encontró archivo Excel. Simulando con datos proporcionados...\n\n";
    
    // Simular datos exactos del Excel
    $headerRow = ['MZNA', 'LOTE', 'PRECIO', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
    $financingRow = ['', '', '', '24', '36', '48', '60', '72', 'CONTADO', '84', '96', '108', '55', '120', '132'];
    
    echo "📊 DATOS SIMULADOS:\n";
    echo "Headers: " . implode(', ', $headerRow) . "\n";
    echo "Financing: " . implode(', ', $financingRow) . "\n\n";
} else {
    echo "📖 Leyendo archivo Excel real...\n";
    
    try {
        $spreadsheet = IOFactory::load($excelFile);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Leer fila de headers (fila 1)
        $headerRow = [];
        $highestColumn = $worksheet->getHighestColumn();
        $columnIndex = 1;
        
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $cellValue = $worksheet->getCell($col . '1')->getValue();
            $headerRow[] = $cellValue;
            $columnIndex++;
        }
        
        // Leer fila de financiamiento (fila 2)
        $financingRow = [];
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $cellValue = $worksheet->getCell($col . '2')->getValue();
            $financingRow[] = $cellValue;
        }
        
        echo "📊 DATOS DEL ARCHIVO REAL:\n";
        echo "Headers: " . implode(', ', array_slice($headerRow, 0, 15)) . "\n";
        echo "Financing: " . implode(', ', array_slice($financingRow, 0, 15)) . "\n\n";
        
    } catch (Exception $e) {
        echo "❌ Error leyendo Excel: " . $e->getMessage() . "\n";
        echo "Usando datos simulados...\n\n";
        
        // Fallback a datos simulados
        $headerRow = ['MZNA', 'LOTE', 'PRECIO', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
        $financingRow = ['', '', '', '24', '36', '48', '60', '72', 'CONTADO', '84', '96', '108', '55', '120', '132'];
    }
}

// Simular exactamente el método extractFinancingRules
echo "=== SIMULACIÓN DE extractFinancingRules ===\n\n";

$financingRules = [];
$columnMapping = [];

echo "🔍 ANALIZANDO CADA HEADER:\n";

foreach ($headerRow as $index => $originalHeader) {
    $cleanHeader = trim(strtoupper($originalHeader));
    
    echo "\nColumna {$index}:\n";
    echo "  - Valor original: '{$originalHeader}'\n";
    echo "  - Valor limpio: '{$cleanHeader}'\n";
    echo "  - Longitud: " . strlen($cleanHeader) . "\n";
    echo "  - Es letra única A-Z: " . (preg_match('/^[A-Z]$/', $cleanHeader) ? '✅ SÍ' : '❌ NO') . "\n";
    
    if ($cleanHeader === 'J') {
        echo "  - 🎯 ESTA ES LA MANZANA J 🎯\n";
    }
    
    // Aplicar la lógica exacta del código
    if (preg_match('/^[A-Z]$/', $cleanHeader)) {
        $manzanaLetter = $cleanHeader;
        $columnMapping[$manzanaLetter] = $index;
        
        // Obtener el valor de financiamiento de la fila 2
        $financingValue = isset($financingRow[$index]) ? trim($financingRow[$index]) : '';
        
        echo "  - 💰 Valor de financiamiento: '{$financingValue}'\n";
        echo "  - Es numérico: " . (is_numeric($financingValue) ? '✅ SÍ' : '❌ NO') . "\n";
        echo "  - Es CONTADO: " . (strtoupper($financingValue) === 'CONTADO' ? '✅ SÍ' : '❌ NO') . "\n";
        echo "  - Es CASH: " . (strtoupper($financingValue) === 'CASH' ? '✅ SÍ' : '❌ NO') . "\n";
        
        if (is_numeric($financingValue)) {
            echo "  - Valor numérico: " . (int)$financingValue . "\n";
            echo "  - Es mayor que 0: " . ((int)$financingValue > 0 ? '✅ SÍ' : '❌ NO') . "\n";
        }
        
        // Determinar tipo de financiamiento basado en el valor
        if (strtoupper($financingValue) === 'CONTADO' || strtoupper($financingValue) === 'CASH') {
            // Es pago al contado
            $financingRules[$manzanaLetter] = [
                'type' => 'cash_only',
                'installments' => null,
                'column_index' => $index
            ];
            echo "  - ✅ CONFIGURADA COMO: CONTADO\n";
        } elseif (is_numeric($financingValue) && (int)$financingValue > 0) {
            // Es financiamiento en cuotas
            $installments = (int)$financingValue;
            $financingRules[$manzanaLetter] = [
                'type' => 'installments',
                'installments' => $installments,
                'column_index' => $index
            ];
            echo "  - ✅ CONFIGURADA COMO: CUOTAS ({$installments})\n";
        } else {
            // Valor no válido, saltar esta manzana
            echo "  - ❌ SALTADA - Valor no válido\n";
            echo "  - Razón: No es CONTADO ni numérico válido\n";
            unset($columnMapping[$manzanaLetter]);
        }
    }
}

echo "\n\n=== RESULTADO FINAL ===\n";
echo "📊 Total manzanas detectadas: " . count($financingRules) . "\n";
echo "🏠 Manzanas detectadas: " . implode(', ', array_keys($financingRules)) . "\n";
echo "🎯 ¿Manzana J detectada?: " . (isset($financingRules['J']) ? '✅ SÍ' : '❌ NO') . "\n";

if (isset($financingRules['J'])) {
    echo "\n🎉 MANZANA J CONFIGURADA CORRECTAMENTE:\n";
    echo "  - Tipo: " . $financingRules['J']['type'] . "\n";
    echo "  - Cuotas: " . ($financingRules['J']['installments'] ?? 'N/A') . "\n";
    echo "  - Índice de columna: " . $financingRules['J']['column_index'] . "\n";
} else {
    echo "\n❌ PROBLEMA: MANZANA J NO FUE DETECTADA\n";
    echo "Esto explica por qué no aparece en la lista de manzanas disponibles.\n";
}

echo "\n=== DIAGNÓSTICO COMPLETADO ===\n";