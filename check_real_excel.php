<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

echo "=== VERIFICACIÓN DEL ARCHIVO EXCEL REAL ===\n\n";

$file = 'storage/app/private/temp/lot-imports/1755232986_Libro2.xlsx';

if (file_exists($file)) {
    echo "✅ Archivo encontrado: $file\n\n";
    
    try {
        $spreadsheet = IOFactory::load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $headers = $worksheet->rangeToArray('A1:Z1')[0];
        
        // Filtrar headers vacíos
        $headers = array_filter($headers, function($h) {
            return !is_null($h) && $h !== '';
        });
        
        echo "📊 Total columnas: " . count($headers) . "\n";
        echo "📋 Headers encontrados:\n";
        
        foreach ($headers as $index => $header) {
            echo "  [$index] '$header'\n";
        }
        
        echo "\n🔍 ¿Existe columna J?\n";
        $hasJ = in_array('J', $headers);
        echo $hasJ ? "✅ SÍ - Columna J encontrada" : "❌ NO - Columna J NO encontrada";
        echo "\n\n";
        
        // Verificar valores de la fila 2
        echo "💰 Valores de financiamiento (fila 2):\n";
        $values = $worksheet->rangeToArray('A2:Z2')[0];
        $values = array_slice($values, 0, count($headers));
        
        foreach ($values as $index => $value) {
            $header = array_values($headers)[$index] ?? "Col$index";
            echo "  $header: '$value'\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error al leer archivo: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Archivo no encontrado: $file\n";
    echo "\n📁 Archivos disponibles en lot-imports:\n";
    
    $dir = 'storage/app/private/temp/lot-imports/';
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $f) {
            if (pathinfo($f, PATHINFO_EXTENSION) === 'xlsx') {
                echo "  - $f\n";
            }
        }
    }
}

echo "\n=== VERIFICACIÓN COMPLETADA ===\n";