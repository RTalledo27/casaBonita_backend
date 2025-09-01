<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CreateTestExcel extends Command
{
    protected $signature = 'create:test-excel {filename?}';
    protected $description = 'Create test Excel file with real advisor data';

    public function handle()
    {
        $filename = $this->argument('filename') ?? 'contratos_prueba_real.xlsx';
        $filePath = storage_path('app/public/imports/' . $filename);
        
        // Ensure directory exists
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Headers (16 columns as per the mapping)
        $headers = [
            'TIPO_OPERACION',
            'ESTADO_CONTRATO', 
            'CLIENTE_NOMBRE_COMPLETO',
            'CLIENTE_TIPO_DOC',
            'CLIENTE_NUM_DOC',
            'CLIENTE_EMAIL',
            'CLIENTE_TELEFONO_1',
            'LOTE_NUMERO',
            'LOTE_MANZANA',
            'VENTA_FECHA_FIRMA',
            'VENTA_NUMERO_CONTRATO',
            'ASESOR_NOMBRE',
            'ASESOR_CODIGO',
            'ASESOR_EMAIL',
            'FECHA_VENTA',
            'OBSERVACIONES'
        ];
        
        // Set headers
        foreach ($headers as $col => $header) {
            $sheet->setCellValue(chr(65 + $col) . '1', $header);
        }
        
        // Test data with REAL advisors from database
        $testData = [
            [
                'contrato',
                'ACTIVO',
                'LUZ AURORA ARMIJOS ROBLEDO',
                'DNI',
                '12345678',
                'luz.armijos@email.com',
                '987654321',
                '1',
                'A',
                '2024-01-15',
                'CONT-2024-001',
                'LUIS ENRIQUE TAVARA CASTILLO', // Real advisor ID 1
                'EMP4147', // Real code
                'luis.tavara@casabonita.com',
                '2024-01-15',
                'Prueba con asesor real'
            ],
            [
                'contrato',
                'VIGENTE',
                'MARIA GONZALEZ LOPEZ',
                'DNI',
                '87654321',
                'maria.gonzalez@email.com',
                '123456789',
                '2',
                'B',
                '2024-01-16',
                'CONT-2024-002',
                'PAOLA JUDITH CANDELA NEIRA', // Real advisor ID 6
                'EMP3538', // Real code
                'paola.candela@casabonita.com',
                '2024-01-16',
                'Prueba con asesor real 2'
            ],
            [
                'contrato',
                'FIRMADO',
                'CARLOS RODRIGUEZ SILVA',
                'DNI',
                '11223344',
                'carlos.rodriguez@email.com',
                '555666777',
                '3',
                'C',
                '2024-01-17',
                'CONT-2024-003',
                'DANIELA AIRAM MERINO VALIENTE', // Real advisor ID 7 - FULL NAME
                'EMP0097', // Real code
                'daniela.merino@casabonita.com',
                '2024-01-17',
                'Prueba con asesor real 3'
            ]
        ];
        
        // Add test data
        foreach ($testData as $rowIndex => $rowData) {
            foreach ($rowData as $col => $value) {
                $sheet->setCellValue(chr(65 + $col) . ($rowIndex + 2), $value);
            }
        }
        
        // Save file
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
        
        $this->info("Test Excel file created: {$filePath}");
        $this->info("File contains 3 rows with REAL advisor data:");
        $this->info("- LUIS ENRIQUE TAVARA CASTILLO (EMP4147)");
        $this->info("- PAOLA JUDITH CANDELA NEIRA (EMP3538)");
        $this->info("- DANIELA AIRAM MERINO VALIENTE (EMP0097)");
        
        return 0;
    }
}