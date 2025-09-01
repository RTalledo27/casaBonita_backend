<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Crear un nuevo spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Headers según lo especificado por el usuario
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

// Datos de prueba
$testData = [
    [
        'Juan Pérez',
        'ASE001',
        'juan.perez@email.com',
        'María García López',
        'CC',
        '12345678',
        '3001234567',
        'maria.garcia@email.com',
        '15',
        'A',
        '2024-01-15',
        'CONTRATO',
        'Contrato de prueba',
        'ACTIVO'
    ],
    [
        'Ana Rodríguez',
        'ASE002', 
        'ana.rodriguez@email.com',
        'Carlos Martínez Silva',
        'CC',
        '87654321',
        '3009876543',
        'carlos.martinez@email.com',
        '20',
        'B',
        '2024-01-16',
        'CONTRATO',
        'Segundo contrato de prueba',
        'ACTIVO'
    ]
];

// Escribir datos de prueba
foreach ($testData as $rowIndex => $rowData) {
    foreach ($rowData as $colIndex => $value) {
        $sheet->setCellValue(chr(65 + $colIndex) . ($rowIndex + 2), $value);
    }
}

// Guardar el archivo
$writer = new Xlsx($spreadsheet);
$filePath = 'storage/app/test_contracts_simplified.xlsx';
$writer->save($filePath);

echo "Archivo Excel creado exitosamente: {$filePath}\n";
echo "Headers: " . implode(', ', $headers) . "\n";
echo "Filas de datos: " . count($testData) . "\n";