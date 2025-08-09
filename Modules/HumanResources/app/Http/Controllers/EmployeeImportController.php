<?php

namespace Modules\HumanResources\Http\Controllers;

use Illuminate\Http\Request;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Modules\HumanResources\Services\EmployeeImportService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EmployeeImportController
{
    protected $importService;

    public function __construct(EmployeeImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Validar archivo Excel antes de importar
     */
    public function validateImport(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls|max:10240' // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo inválido',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            
            // Leer archivo Excel
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo está vacío'
                ], 422);
            }

            // Validar headers
            $headers = array_map('trim', $data[0]);
            $structureValidation = $this->importService->validateExcelStructure($headers);
            
            if (!$structureValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $structureValidation['error']
                ], 422);
            }

            // Procesar datos para preview
            $processedData = [];
            $dataRows = array_slice($data, 1); // Remover header
            
            foreach (array_slice($dataRows, 0, 5) as $index => $row) { // Solo primeras 5 filas para preview
                $rowData = array_combine($headers, $row);
                $processedData[] = [
                    'fila' => $index + 2,
                    'colaborador' => $rowData['COLABORADOR'] ?? '',
                    'dni' => $rowData['DNI'] ?? '',
                    'correo' => $rowData['CORREO'] ?? '',
                    'cargo' => $rowData['CARGO'] ?? '',
                    'fecha_inicio' => $rowData['FECHA DE INICIO'] ?? '',
                    'sueldo_basico' => $rowData['SUELDO BASICO'] ?? ''
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Archivo válido',
                'data' => [
                    'total_rows' => count($dataRows),
                    'headers' => $headers,
                    'preview' => $processedData
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Importar empleados desde Excel
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls|max:10240'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo inválido',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            
            // Leer archivo Excel
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            if (empty($data) || count($data) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo debe contener al menos una fila de datos'
                ], 422);
            }

            // Procesar datos
            $headers = array_map('trim', $data[0]);
            $dataRows = array_slice($data, 1);
            
            $processedData = [];
            foreach ($dataRows as $row) {
                if (!empty(array_filter($row))) { // Ignorar filas vacías
                    $processedData[] = array_combine($headers, $row);
                }
            }

            if (empty($processedData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron datos válidos para importar'
                ], 422);
            }

            // Importar datos
            $result = $this->importService->importFromExcel($processedData);

            $statusCode = $result['success'] > 0 ? 200 : 422;
            
            return response()->json([
                'success' => $result['success'] > 0,
                'message' => $this->getImportMessage($result),
                'data' => [
                    'imported' => $result['success'],
                    'errors' => $result['errors'],
                    'created_users' => $result['created_users'],
                    'created_employees' => $result['created_employees']
                ]
            ], $statusCode);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error durante la importación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar mensaje de resultado de importación
     */
    private function getImportMessage(array $result): string
    {
        $imported = $result['success'];
        $errors = count($result['errors']);
        
        if ($imported > 0 && $errors === 0) {
            return "Se importaron exitosamente {$imported} empleados.";
        } elseif ($imported > 0 && $errors > 0) {
            return "Se importaron {$imported} empleados con {$errors} errores.";
        } else {
            return "No se pudo importar ningún empleado. Revisa los errores.";
        }
    }

    /**
     * Descargar plantilla de Excel
     */
    public function downloadTemplate()
    {   
        try {
            // Crear nuevo spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Configurar headers
            $headers = [
                'N°',
                'COLABORADOR',
                'SUNAT',
                'FECHA NAC',
                'CORREO',
                'DNI',
                'AFP',
                'CUSPP',
                'CARGO',
                'FECHA DE INICIO',
                'SUELDO BASICO',
                'DÍAS',
                'SUELDO'
            ];
            
            // Escribir headers en la primera fila
            $sheet->fromArray($headers, null, 'A1');
            
            // Datos de ejemplo
            $sampleData = [
                '1',
                'JUAN CARLOS PÉREZ GARCÍA',
                '12345678901',
                '15/03/1990',
                'juan.perez@example.com',
                '12345678',
                'PRIMA',
                '1234567890123',
                'ASESOR INMOBILIARIO',
                '01/01/2024',
                '2500.00',
                '30',
                '2500.00'
            ];
            
            // Escribir datos de ejemplo en la segunda fila
            $sheet->fromArray($sampleData, null, 'A2');
            
            // Aplicar estilos a los headers
            $headerRange = 'A1:' . $sheet->getHighestColumn() . '1';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2E8F0');
            
            // Ajustar ancho de columnas
            foreach (range('A', $sheet->getHighestColumn()) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            
            // Crear writer
            $writer = new Xlsx($spreadsheet);
            
            // Configurar headers de respuesta
            $filename = 'plantilla_empleados.xlsx';
            
            return response()->stream(
                function () use ($writer) {
                    $writer->save('php://output');
                },
                200,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Cache-Control' => 'max-age=0',
                    'Pragma' => 'public'
                ]
            );
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar plantilla: ' . $e->getMessage()
            ], 500);
        }
    }
}