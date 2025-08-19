<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Script de diagnóstico específico para analizar la manzana J
 * Analiza por qué la manzana J se está saltando durante la importación
 */

echo "=== DIAGNÓSTICO ESPECÍFICO MANZANA J ===\n\n";

// Simulación basada en los datos proporcionados por el usuario
echo "📁 Simulando análisis basado en datos proporcionados por el usuario\n";
echo "📊 La manzana J tiene 55 lotes con cuotas específicas\n\n";

// Datos simulados basados en la información del usuario
$headerRow = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
$financingRow = ['CONTADO', '72', '48', 'no disponible', 'CONTADO', '60', '84', '96', '108', '55', 'CONTADO', '36'];
$dataRow = ['456.40', '456.40', '456.40', '0', '456.40', '456.40', '456.40', '456.40', '456.40', '456.40', '456.40', '456.40'];

echo "📋 Simulando análisis de Excel con datos reales\n\n";
echo "🔍 Analizando manzanas A-L\n\n";

echo "=== ANÁLISIS DE HEADERS (Fila 1) ===\n";
    $jColumnIndex = null;
    
    foreach ($headerRow as $index => $header) {
        $cleanHeader = trim($header);
        $isJ = ($cleanHeader === 'J');
        
        if ($isJ) {
            $jColumnIndex = $index;
            echo "🎯 COLUMNA J ENCONTRADA:\n";
        } else {
            echo "Columna {$index}: ";
        }
        
        echo "  - Índice: {$index}\n";
        echo "  - Valor original: '" . ($header ?? 'NULL') . "'\n";
        echo "  - Valor limpio: '" . $cleanHeader . "'\n";
        echo "  - Longitud: " . strlen($cleanHeader) . "\n";
        echo "  - Es letra única A-Z: " . (preg_match('/^[A-Z]$/', $cleanHeader) ? '✅ SÍ' : '❌ NO') . "\n";
        echo "  - Códigos ASCII: [" . implode(', ', array_map('ord', str_split($cleanHeader))) . "]\n";
        
        if ($isJ) {
            echo "  - 🔥 ESTA ES LA MANZANA J 🔥\n";
        }
        
        echo "\n";
    }
    
    if ($jColumnIndex === null) {
        echo "❌ ERROR CRÍTICO: No se encontró la columna J en los headers\n";
        echo "Headers encontrados: " . implode(', ', array_map(function($h) { return "'$h'"; }, $headerRow)) . "\n";
        exit(1);
    }
    
    echo "\n=== ANÁLISIS DEL VALOR DE FINANCIAMIENTO PARA MANZANA J (Fila 2) ===\n";
    
    $jFinancingValue = $financingRow[$jColumnIndex] ?? null;
    $jFinancingValueClean = trim($jFinancingValue);
    
    echo "🎯 VALOR DE FINANCIAMIENTO PARA MANZANA J:\n";
    echo "  - Índice de columna: {$jColumnIndex}\n";
    echo "  - Valor original: '" . ($jFinancingValue ?? 'NULL') . "'\n";
    echo "  - Valor limpio: '" . $jFinancingValueClean . "'\n";
    echo "  - Longitud: " . strlen($jFinancingValueClean) . "\n";
    echo "  - Está vacío: " . (empty($jFinancingValueClean) ? '✅ SÍ' : '❌ NO') . "\n";
    echo "  - Es numérico: " . (is_numeric($jFinancingValueClean) ? '✅ SÍ' : '❌ NO') . "\n";
    
    if (is_numeric($jFinancingValueClean)) {
        $numericValue = (int)$jFinancingValueClean;
        echo "  - Valor numérico: {$numericValue}\n";
        echo "  - Es mayor que 0: " . ($numericValue > 0 ? '✅ SÍ' : '❌ NO') . "\n";
    }
    
    echo "  - Es 'CONTADO': " . (strtoupper($jFinancingValueClean) === 'CONTADO' ? '✅ SÍ' : '❌ NO') . "\n";
    echo "  - Es 'CASH': " . (strtoupper($jFinancingValueClean) === 'CASH' ? '✅ SÍ' : '❌ NO') . "\n";
    echo "  - Códigos ASCII: [" . implode(', ', array_map('ord', str_split($jFinancingValueClean))) . "]\n";
    
    echo "\n=== SIMULACIÓN DE LÓGICA DE DETECCIÓN ===\n";
    
    $shouldBeDetected = false;
    $detectionReason = '';
    
    if (strtoupper($jFinancingValueClean) === 'CONTADO' || strtoupper($jFinancingValueClean) === 'CASH') {
        $shouldBeDetected = true;
        $detectionReason = 'Pago al contado';
    } elseif (is_numeric($jFinancingValueClean) && (int)$jFinancingValueClean > 0) {
        $shouldBeDetected = true;
        $detectionReason = 'Financiamiento en cuotas: ' . (int)$jFinancingValueClean;
    } else {
        $shouldBeDetected = false;
        $detectionReason = 'Valor no válido - no es CONTADO ni numérico > 0';
    }
    
    echo "🔍 RESULTADO DE LA SIMULACIÓN:\n";
    echo "  - ¿Debería ser detectada?: " . ($shouldBeDetected ? '✅ SÍ' : '❌ NO') . "\n";
    echo "  - Razón: {$detectionReason}\n";
    
    if (!$shouldBeDetected) {
        echo "\n❌ PROBLEMA IDENTIFICADO:\n";
        echo "La manzana J se está saltando porque el valor '{$jFinancingValueClean}' no cumple con las condiciones:\n";
        echo "  1. No es 'CONTADO' o 'CASH'\n";
        echo "  2. No es un número válido mayor que 0\n";
        
        echo "\n🔧 POSIBLES SOLUCIONES:\n";
        echo "  1. Verificar que el valor en la fila 2, columna J sea un número (ej: 55, 72, etc.)\n";
        echo "  2. Verificar que no haya espacios o caracteres especiales\n";
        echo "  3. Verificar que la celda no esté formateada como texto\n";
    } else {
        echo "\n✅ LA MANZANA J DEBERÍA SER DETECTADA CORRECTAMENTE\n";
        echo "Si aún así se está saltando, el problema podría estar en:\n";
        echo "  1. La lógica de detección en el código\n";
        echo "  2. Problemas de codificación de caracteres\n";
        echo "  3. Diferencias en el procesamiento del archivo\n";
    }
    
    echo "\n=== MUESTRA DE DATOS DE LOTES (Fila 3) ===\n";
    
    $jDataValue = $dataRow[$jColumnIndex] ?? null;
    echo "🎯 PRIMER LOTE DE MANZANA J:\n";
    echo "  - Valor: '" . ($jDataValue ?? 'NULL') . "'\n";
    echo "  - Tipo: " . gettype($jDataValue) . "\n";
    
    echo "\n=== RESUMEN FINAL ===\n";
    echo "📊 Columna J encontrada en índice: {$jColumnIndex}\n";
    echo "💰 Valor de financiamiento: '{$jFinancingValueClean}'\n";
    echo "🎯 Estado de detección: " . ($shouldBeDetected ? '✅ DEBERÍA FUNCIONAR' : '❌ PROBLEMA IDENTIFICADO') . "\n";
    echo "📝 Razón: {$detectionReason}\n";

echo "\n=== DIAGNÓSTICO COMPLETADO ===\n";