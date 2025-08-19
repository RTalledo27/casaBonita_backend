<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use PhpOffice\PhpSpreadsheet\IOFactory;

echo "=== LECTURA DEL EXCEL DE PRUEBA ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

$filePath = 'storage/app/public/imports/contratos_prueba_real.xlsx';

if (!file_exists($filePath)) {
    echo "❌ Archivo no encontrado: {$filePath}\n";
    exit(1);
}

try {
    $spreadsheet = IOFactory::load($filePath);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
    
    echo "📊 CONTENIDO DEL EXCEL:\n";
    echo "Total de filas: " . count($rows) . "\n\n";
    
    // Mostrar headers
    if (count($rows) > 0) {
        echo "📋 HEADERS (Fila 1):\n";
        foreach ($rows[0] as $index => $header) {
            echo "  Columna " . ($index + 1) . ": '{$header}'\n";
        }
        echo "\n";
    }
    
    // Mostrar datos (filas 2 en adelante)
    echo "📝 DATOS:\n";
    for ($i = 1; $i < count($rows); $i++) {
        echo "\n--- FILA " . ($i + 1) . " ---\n";
        $row = $rows[$i];
        
        // Mostrar datos relevantes para debug
        echo "  NOMBRE_CLIENTE: '{$row[0]}'\n";
        echo "  TELEFONO: '{$row[1]}'\n";
        echo "  EMAIL: '{$row[2]}'\n";
        echo "  MANZANA: '{$row[3]}'\n";
        echo "  LOTE: '{$row[4]}'\n";
        echo "  ASESOR: '{$row[5]}'\n";
        echo "  PRECIO_TOTAL: '{$row[6]}'\n";
        echo "  CUOTA_INICIAL: '{$row[7]}'\n";
        echo "  TASA_INTERES: '{$row[8]}'\n";
        echo "  PLAZO_MESES: '{$row[9]}'\n";
        echo "  CUOTA_MENSUAL: '{$row[10]}'\n";
        echo "  FECHA_FIRMA: '{$row[11]}'\n";
        echo "  ESTADO: '{$row[12]}'\n";
        echo "  NOTAS: '{$row[13]}'\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error al leer el Excel: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE LA LECTURA ===\n";