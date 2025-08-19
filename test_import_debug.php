<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Crear archivo Excel de prueba con datos que simulen el problema
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Headers requeridos
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
foreach ($headers as $col => $header) {
    $sheet->setCellValue(chr(65 + $col) . '1', $header);
}

// Datos de prueba que simulan diferentes escenarios
$testData = [
    // Casos que DEBERÍAN procesarse (6 exitosos)
    ['Juan Pérez', 'A001', 'juan@test.com', 'María García López', 'DNI', '12345678', '987654321', 'maria@test.com', '001', 'A', '2024-01-15', 'venta', 'Contrato normal', 'vigente'],
    ['Ana Silva', 'A002', 'ana@test.com', 'Carlos Rodríguez', 'DNI', '87654321', '123456789', 'carlos@test.com', '002', 'B', '2024-01-16', 'contrato', 'Segundo contrato', 'activo'],
    ['Luis Torres', 'A003', 'luis@test.com', 'Elena Martínez', 'DNI', '11223344', '555666777', 'elena@test.com', '003', 'C', '2024-01-17', 'venta', 'Tercer contrato', 'firmado'],
    ['Rosa Jiménez', 'A004', 'rosa@test.com', 'Pedro Sánchez', 'DNI', '44332211', '777888999', 'pedro@test.com', '004', 'D', '2024-01-18', 'reserva', 'Cuarto contrato', 'vigente'],
    ['Miguel Vargas', 'A005', 'miguel@test.com', 'Laura Fernández', 'DNI', '55667788', '111222333', 'laura@test.com', '005', 'E', '2024-01-19', 'otro', 'Quinto contrato', 'activo'],
    ['Carmen López', 'A006', 'carmen@test.com', 'Roberto Díaz', 'DNI', '99887766', '444555666', 'roberto@test.com', '006', 'F', '2024-01-20', 'venta', 'Sexto contrato', 'firmado'],
    
    // Casos que se OMITEN por estado vacío (71 omitidas)
    ['Asesor1', 'A007', 'asesor1@test.com', 'Cliente1', 'DNI', '12312312', '987987987', 'cliente1@test.com', '007', 'G', '2024-01-21', 'reserva', 'Sin estado', ''],
    ['Asesor2', 'A008', 'asesor2@test.com', 'Cliente2', 'DNI', '45645645', '654654654', 'cliente2@test.com', '008', 'H', '2024-01-22', 'consulta', 'Sin estado', ''],
    ['Asesor3', 'A009', 'asesor3@test.com', 'Cliente3', 'DNI', '78978978', '321321321', 'cliente3@test.com', '009', 'I', '2024-01-23', 'visita', 'Sin estado', ''],
    
    // Casos que dan ERROR (65 errores) - datos problemáticos
    ['', '', '', 'Cliente Sin Asesor', 'DNI', '11111111', '999999999', 'error1@test.com', '010', 'J', '2024-01-24', 'venta', 'Error asesor', 'vigente'],
    ['Asesor Error', 'A010', 'error@test.com', '', 'DNI', '22222222', '888888888', 'error2@test.com', '011', 'K', '2024-01-25', 'venta', 'Error cliente', 'vigente'],
    ['Asesor Lote', 'A011', 'lote@test.com', 'Cliente Lote Error', 'DNI', '33333333', '777777777', 'error3@test.com', '', 'L', '2024-01-26', 'venta', 'Error lote', 'vigente'],
    ['Asesor Doc', 'A012', 'doc@test.com', 'Cliente Doc Error', '', '44444444', '666666666', 'error4@test.com', '012', 'M', '2024-01-27', 'venta', 'Error doc', 'vigente'],
    ['Asesor Tel', 'A013', 'tel@test.com', 'Cliente Tel Error', 'DNI', '55555555', '', 'error5@test.com', '013', 'N', '2024-01-28', 'venta', 'Error tel', 'vigente'],
];

// Agregar más filas para simular el volumen real
$row = 2;
foreach ($testData as $data) {
    foreach ($data as $col => $value) {
        $sheet->setCellValue(chr(65 + $col) . $row, $value);
    }
    $row++;
}

// Agregar más filas omitidas para llegar a 71
for ($i = 0; $i < 66; $i++) {
    $sheet->setCellValue('A' . $row, "Asesor" . ($i + 100));
    $sheet->setCellValue('B' . $row, "A" . ($i + 100));
    $sheet->setCellValue('C' . $row, "asesor" . ($i + 100) . "@test.com");
    $sheet->setCellValue('D' . $row, "Cliente Omitido " . ($i + 1));
    $sheet->setCellValue('E' . $row, "DNI");
    $sheet->setCellValue('F' . $row, "1234567" . $i);
    $sheet->setCellValue('G' . $row, "98765432" . $i);
    $sheet->setCellValue('H' . $row, "cliente" . ($i + 100) . "@test.com");
    $sheet->setCellValue('I' . $row, "0" . ($i + 100));
    $sheet->setCellValue('J' . $row, "Z");
    $sheet->setCellValue('K' . $row, "2024-01-" . (10 + $i % 20));
    $sheet->setCellValue('L' . $row, "reserva"); // No es venta/contrato
    $sheet->setCellValue('M' . $row, "Observación " . ($i + 1));
    $sheet->setCellValue('N' . $row, ""); // Estado vacío
    $row++;
}

// Agregar más filas con errores para llegar a 65
for ($i = 0; $i < 60; $i++) {
    $sheet->setCellValue('A' . $row, "AsesorError" . ($i + 200));
    $sheet->setCellValue('B' . $row, "E" . ($i + 200));
    $sheet->setCellValue('C' . $row, "error" . ($i + 200) . "@test.com");
    $sheet->setCellValue('D' . $row, "Cliente Error " . ($i + 1));
    $sheet->setCellValue('E' . $row, "DNI");
    $sheet->setCellValue('F' . $row, ""); // Documento vacío para causar error
    $sheet->setCellValue('G' . $row, "55555555" . $i);
    $sheet->setCellValue('H' . $row, "error" . ($i + 200) . "@test.com");
    $sheet->setCellValue('I' . $row, "9" . ($i + 200));
    $sheet->setCellValue('J' . $row, "X");
    $sheet->setCellValue('K' . $row, "2024-01-" . (10 + $i % 20));
    $sheet->setCellValue('L' . $row, "venta");
    $sheet->setCellValue('M' . $row, "Error " . ($i + 1));
    $sheet->setCellValue('N' . $row, "vigente");
    $row++;
}

// Guardar archivo
$writer = new Xlsx($spreadsheet);
$filename = 'test_contracts_debug.xlsx';
$writer->save($filename);

echo "Archivo Excel de prueba creado: {$filename}\n";
echo "Total de filas: " . ($row - 1) . "\n";
echo "\nEstructura del archivo:\n";
echo "- 6 filas que deberían procesarse exitosamente\n";
echo "- 71 filas que deberían omitirse (estado vacío + tipo operación no válido)\n";
echo "- 65 filas que deberían dar error (datos faltantes)\n";
echo "\nAhora ejecuta: php debug_row_processing.php {$filename}\n";