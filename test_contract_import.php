<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Modules\Sales\Services\ContractImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

echo "=== CREANDO ARCHIVO DE PRUEBA PARA IMPORTACIÓN DE CONTRATOS ===\n\n";

// Crear archivo Excel de prueba con los 14 campos correctos
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Headers correctos según los 14 campos definidos
$headers = [
    'ASESOR_NOMBRE',
    'ASESOR_CODIGO', 
    'ASESOR_EMAIL',
    'CLIENTE_NOMBRE_COMPLETO',
    'CLIENTE_TIPO_DOC',
    'CLIENTE_NUM_DOC',
    'CLIENTE_TELEFONO_1',
    'CLIENTE_EMAIL',
    'LOTE_NUMERO',
    'LOTE_MANZANA',
    'FECHA_VENTA',
    'TIPO_OPERACION',
    'OBSERVACIONES',
    'ESTADO_CONTRATO'
];

// Escribir headers
foreach ($headers as $index => $header) {
    $sheet->setCellValue(chr(65 + $index) . '1', $header);
}

// Datos de prueba - CONTRATO que debería crear contrato
$testData = [
    'Juan Pérez',           // ASESOR_NOMBRE
    'ASE001',              // ASESOR_CODIGO
    'juan@test.com',       // ASESOR_EMAIL
    'María García López',   // CLIENTE_NOMBRE_COMPLETO
    'DNI',                 // CLIENTE_TIPO_DOC
    '12345678',            // CLIENTE_NUM_DOC
    '987654321',           // CLIENTE_TELEFONO_1
    'maria@test.com',      // CLIENTE_EMAIL
    '1',                   // LOTE_NUMERO
    '1',                   // LOTE_MANZANA
    '2024-01-15',          // FECHA_VENTA
    'CONTRATO',            // TIPO_OPERACION (esto debería crear contrato)
    'Contrato de prueba',  // OBSERVACIONES
    'VIGENTE'              // ESTADO_CONTRATO
];

// Escribir datos de prueba
foreach ($testData as $index => $value) {
    $sheet->setCellValue(chr(65 + $index) . '2', $value);
}

// Datos de prueba - RESERVA que NO debería crear contrato
$testData2 = [
    'Ana Martín',          // ASESOR_NOMBRE
    'ASE002',              // ASESOR_CODIGO
    'ana@test.com',        // ASESOR_EMAIL
    'Carlos Ruiz Díaz',    // CLIENTE_NOMBRE_COMPLETO
    'DNI',                 // CLIENTE_TIPO_DOC
    '87654321',            // CLIENTE_NUM_DOC
    '123456789',           // CLIENTE_TELEFONO_1
    'carlos@test.com',     // CLIENTE_EMAIL
    '2',                   // LOTE_NUMERO
    '1',                   // LOTE_MANZANA
    '2024-01-16',          // FECHA_VENTA
    'RESERVA',             // TIPO_OPERACION (esto NO debería crear contrato)
    'Solo reserva',        // OBSERVACIONES
    ''                     // ESTADO_CONTRATO (vacío para reserva)
];

// Escribir segunda fila de datos
foreach ($testData2 as $index => $value) {
    $sheet->setCellValue(chr(65 + $index) . '3', $value);
}

// Guardar archivo
$filename = 'test_contract_import_' . date('Y-m-d_H-i-s') . '.xlsx';
$filepath = storage_path('app/temp/' . $filename);

// Crear directorio si no existe
if (!file_exists(dirname($filepath))) {
    mkdir(dirname($filepath), 0755, true);
}

$writer = new Xlsx($spreadsheet);
$writer->save($filepath);

echo "Archivo creado: {$filepath}\n\n";

// Probar la importación
echo "=== PROBANDO IMPORTACIÓN ===\n\n";

try {
    $importService = new ContractImportService();
    
    // Simular UploadedFile
    $uploadedFile = new UploadedFile(
        $filepath,
        $filename,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );
    
    echo "Iniciando importación con processExcelSimplified...\n";
    $result = $importService->processExcelSimplified($uploadedFile);
    
    echo "Resultado de la importación:\n";
    echo "- Éxito: " . ($result['success'] ? 'SÍ' : 'NO') . "\n";
    echo "- Mensaje: " . $result['message'] . "\n";
    echo "- Filas procesadas: " . ($result['processed_rows'] ?? 0) . "\n";
    echo "- Reservas creadas: " . ($result['reservations_created'] ?? 0) . "\n";
    echo "- Contratos creados: " . ($result['contracts_created'] ?? 0) . "\n";
    
    if (isset($result['error_details']) && !empty($result['error_details'])) {
        echo "\nErrores encontrados:\n";
        foreach ($result['error_details'] as $error) {
            echo "ERROR: {$error['error']}\n";
            echo "Fila: {$error['row']}\n";
            echo "Datos: " . json_encode($error['data']) . "\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

// Limpiar archivo temporal
if (file_exists($filepath)) {
    unlink($filepath);
    echo "\nArchivo temporal eliminado.\n";
}

echo "\n=== PRUEBA COMPLETADA ===\n";