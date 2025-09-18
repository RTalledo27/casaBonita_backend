<?php

namespace Modules\Sales\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Sales\Http\Requests\ContractImportRequest;
use Modules\Sales\Services\ContractImportService;
use Modules\Sales\Models\ContractImportLog;
use Modules\Sales\Jobs\ProcessContractImportJob;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Exception;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Facades\Log;

class ContractImportController extends Controller
{
    private ContractImportService $importService;

    public function __construct(ContractImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Importar contratos desde archivo Excel
     */
    public function import(ContractImportRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->getValidatedData();

            $file = $request->file('file');
            
            // Guardar archivo temporalmente
            $fileName = 'contract_import_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('temp/imports', $fileName, 'local');
            $fullPath = Storage::disk('local')->path($filePath);

            // Procesar archivo usando lógica simplificada
            $result = $this->importService->processExcelSimplified($fullPath);

            // Limpiar archivo temporal
            Storage::disk('local')->delete($filePath);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'processed' => $result['processed'],
                        'errors' => $result['errors'],
                        'error_details' => $result['error_details']
                    ]
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => [
                        'processed' => $result['processed'],
                        'errors' => $result['errors'],
                        'error_details' => $result['error_details']
                    ]
                ], 400);
            }

        } catch (Exception $e) {
            // Limpiar archivo temporal si existe
            if (isset($filePath)) {
                Storage::disk('local')->delete($filePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Importar contratos de forma asíncrona
     */
    public function importAsync(ContractImportRequest $request): JsonResponse
    {
        try {
            // Verificar autenticación del usuario
            $user = $request->user();
            
            // Log de debug para verificar el usuario
            Log::info('ContractImportController::importAsync - Usuario obtenido', [
                'user_exists' => $user !== null,
                'user_id' => $user ? $user->id : null,
                'user_email' => $user ? $user->email : null,
                'auth_guard' => config('auth.defaults.guard'),
                'request_headers' => $request->headers->all()
            ]);
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado. Por favor, inicia sesión nuevamente.'
                ], 401);
            }
            
            if (!$user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de usuario no válido.'
                ], 400);
            }
            
            $validatedData = $request->getValidatedData();
            $file = $request->file('file');
            
            // Guardar archivo temporalmente
            $fileName = 'contract_import_' . time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('temp/imports', $fileName, 'local');
            
            // Log antes de crear el ContractImportLog
            Log::info('ContractImportController::importAsync - Creando ContractImportLog', [
                'user_id' => $user->id,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_path' => $filePath
            ]);
            
            // Crear log de importación con validación adicional
            $importLogData = [
                'user_id' => $user->id,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_path' => $filePath,
                'status' => ContractImportLog::STATUS_PENDING,
                'message' => 'Importación en cola para procesamiento'
            ];
            
            // Verificar que todos los datos requeridos estén presentes
            if (empty($importLogData['user_id'])) {
                throw new \Exception('user_id no puede estar vacío');
            }
            
            $importLog = ContractImportLog::create($importLogData);
            
            // Log después de crear el ContractImportLog
            Log::info('ContractImportController::importAsync - ContractImportLog creado exitosamente', [
                'import_log_id' => $importLog->import_log_id,
                'user_id' => $importLog->user_id
            ]);
            
            // Despachar job asíncrono
            ProcessContractImportJob::dispatch(
                $filePath,
                $user->id,
                $validatedData,
                $importLog->import_log_id
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Importación iniciada. Recibirás una notificación cuando termine.',
                'data' => [
                    'import_log_id' => $importLog->import_log_id,
                    'status' => $importLog->status,
                    'file_name' => $importLog->file_name
                ]
            ], 202); // 202 Accepted
            
        } catch (Exception $e) {
            // Log del error completo
            Log::error('ContractImportController::importAsync - Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar importación asíncrona: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estado de una importación específica
     */
    public function getImportStatus(string $importLogId): JsonResponse
    {
        try {
            $importLog = ContractImportLog::findOrFail($importLogId);
            
            // Verificar que el usuario tenga acceso a este log
            $user = request()->user();
            if ($importLog->user_id !== $user->id && !$user->hasRole(['super_admin', 'administrador'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para ver esta importación'
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Estado de importación obtenido',
                'data' => [
                    'import_log_id' => $importLog->import_log_id,
                    'status' => $importLog->status,
                    'status_label' => $importLog->status_label,
                    'message' => $importLog->message,
                    'file_name' => $importLog->file_name,
                    'file_size' => $importLog->formatted_file_size,
                    'total_rows' => $importLog->total_rows,
                    'processed_rows' => $importLog->processed_rows,
                    'success_count' => $importLog->success_count,
                    'error_count' => $importLog->error_count,
                    'success_rate' => $importLog->success_rate,
                    'processing_time' => $importLog->formatted_processing_time,
                    'started_at' => $importLog->started_at,
                    'completed_at' => $importLog->completed_at,
                    'created_at' => $importLog->created_at,
                    'error_details' => $importLog->error_details,
                    'is_processing' => $importLog->is_processing,
                    'is_successful' => $importLog->is_successful
                ]
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar estructura del archivo Excel simplificado sin procesarlo
     */
    public function validateStructureSimplified(Request $request): JsonResponse
    {
        try {
            // Verificar que el usuario esté autenticado
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'file' => [
                    'required',
                    'file',
                    'mimes:xlsx,xls,csv',
                    'max:51200'
                ]
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $fileName = 'validate_' . time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('temp/imports', $fileName, 'local');
            $fullPath = Storage::disk('local')->path($filePath);

            // Verificar que ZipArchive esté disponible
            if (!class_exists('ZipArchive')) {
                Storage::disk('local')->delete($filePath);
                return response()->json([
                    'success' => false,
                    'message' => 'Error del servidor: La extensión ZIP de PHP no está disponible. Contacte al administrador del sistema.'
                ], 500);
            }
            
            // Leer solo los headers
            $spreadsheet = IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $headers = $worksheet->rangeToArray('A1:' . $worksheet->getHighestColumn() . '1')[0];

            // Validar estructura simplificada
            $validation = $this->importService->validateExcelStructureSimplified($headers);

            // Limpiar archivo temporal
            Storage::disk('local')->delete($filePath);

            return response()->json([
                'success' => $validation['valid'],
                'message' => $validation['valid'] ? 'Estructura válida' : $validation['error'],
                'headers' => $headers,
                'required_headers' => [
                    'ASESOR_NOMBRE', 'ASESOR_CODIGO', 'ASESOR_EMAIL',
                    'CLIENTE_NOMBRE_COMPLETO (o CLIENTE_NOMBRES)', 
                    'CLIENTE_TIPO_DOC (o CLIENTE_TIPO_DOCUMENTO)', 
                    'CLIENTE_NUM_DOC (o CLIENTE_NUMERO_DOCUMENTO)', 
                    'CLIENTE_TELEFONO_1', 'CLIENTE_EMAIL',
                    'LOTE_NUMERO', 'LOTE_MANZANA',
                    'FECHA_VENTA', 'TIPO_OPERACION', 'OBSERVACIONES', 
                    'ESTADO_CONTRATO (o CONTRATO_ESTADO)'
                ]
            ]);

        } catch (Exception $e) {
            if (isset($filePath)) {
                Storage::disk('local')->delete($filePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al validar archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar estructura del archivo Excel sin procesarlo
     */
    public function validateStructure(Request $request): JsonResponse
    {
        try {
            // Verificar que el usuario esté autenticado
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'file' => [
                    'required',
                    'file',
                    'mimes:xlsx,xls,csv',
                    'max:51200'
                ]
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $fileName = 'validate_' . time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('temp/imports', $fileName, 'local');
            $fullPath = Storage::disk('local')->path($filePath);

            // Verificar que ZipArchive esté disponible
            if (!class_exists('ZipArchive')) {
                Storage::disk('local')->delete($filePath);
                return response()->json([
                    'success' => false,
                    'message' => 'Error del servidor: La extensión ZIP de PHP no está disponible. Contacte al administrador del sistema.'
                ], 500);
            }
            
            // Leer solo los headers
            $spreadsheet = IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $headers = $worksheet->rangeToArray('A1:' . $worksheet->getHighestColumn() . '1')[0];

            // Validar estructura
            $validation = $this->importService->validateExcelStructure($headers);

            // Limpiar archivo temporal
            Storage::disk('local')->delete($filePath);

            return response()->json([
                'success' => $validation['valid'],
                'message' => $validation['valid'] ? 'Estructura válida' : $validation['error'],
                'headers' => $headers,
                'required_headers' => [
                    'ASESOR_NOMBRE', 'CLIENTE_NOMBRE_COMPLETO', 'LOTE_NUMERO', 'FECHA_VENTA'
                ],
                'optional_headers' => [
                    'ASESOR_CODIGO', 'ASESOR_EMAIL', 'ASESOR_TELEFONO', 'CANAL_VENTA', 'CAMPANA',
                    'CLIENTE_NOMBRES', 'CLIENTE_TIPO_DOCUMENTO', 'CLIENTE_NUMERO_DOCUMENTO',
                    'CLIENTE_EMAIL', 'CLIENTE_TELEFONO_1', 'CLIENTE_TELEFONO_2', 'CLIENTE_FECHA_NACIMIENTO',
                    'CLIENTE_ESTADO_CIVIL', 'CLIENTE_OCUPACION', 'CLIENTE_SALARIO', 'CLIENTE_TIPO', 'CLIENTE_OBSERVACIONES',
                    'CLIENTE_DIRECCION', 'CLIENTE_REFERENCIA', 'CLIENTE_DISTRITO', 'CLIENTE_PROVINCIA',
                    'CLIENTE_DEPARTAMENTO', 'CLIENTE_CODIGO_POSTAL', 'LOTE_MANZANA', 'LOTE_AREA_TOTAL',
                    'LOTE_AREA_CONSTRUIDA', 'LOTE_PRECIO_TOTAL', 'LOTE_MONEDA', 'LOTE_ESTADO', 'LOTE_OBSERVACIONES',
                    'PRECIO_TOTAL', 'CUOTA_INICIAL', 'MONTO_FINANCIADO', 'TASA_INTERES', 'NUMERO_CUOTAS',
                    'MONTO_CUOTA', 'PAGO_BALLOON', 'SEPARACION', 'DEPOSITO_REFERENCIA', 'FECHA_PAGO_DEPOSITO', 'PAGO_DIRECTO', 'REEMBOLSO',
                    'TOTAL_INICIAL', 'PAGO_INICIAL', 'CONTRATO_NUMERO', 'CONTRATO_TIPO', 'CONTRATO_FECHA_FIRMA',
                    'CONTRATO_FECHA_INICIO', 'CONTRATO_FECHA_FIN', 'CONTRATO_ESTADO', 'CONTRATO_OBSERVACIONES', 'ESTADO_CONTRATO'
                ]
            ]);

        } catch (Exception $e) {
            if (isset($filePath)) {
                Storage::disk('local')->delete($filePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al validar archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar plantilla simplificada de ejemplo para importación
     * Solo incluye 15 campos esenciales, usa Lot Financial Templates para datos financieros
     */
    public function downloadSimplifiedTemplate()
    {
        try {
            // Template simplificado con solo 14 campos esenciales
            $headers = [
                // Sección Asesor (3 campos)
                'ASESOR_NOMBRE',
                'ASESOR_CODIGO',
                'ASESOR_EMAIL',
                
                // Sección Cliente (5 campos) - Solo nombres completos
                'CLIENTE_NOMBRE_COMPLETO',
                'CLIENTE_TIPO_DOC',
                'CLIENTE_NUM_DOC',
                'CLIENTE_TELEFONO_1',
                'CLIENTE_EMAIL',
                
                // Sección Lote (2 campos)
                'LOTE_NUMERO',
                'LOTE_MANZANA',
                
                // Sección Venta y Control (4 campos)
                'FECHA_VENTA',
                'TIPO_OPERACION',
                'OBSERVACIONES',
                'ESTADO_CONTRATO'
            ];

            // Datos de ejemplo simplificados
            $exampleData = [
                [
                    // Asesor
                    'Juan Carlos Pérez',
                    'ASE001',
                    'juan.perez@casabonita.com',
                    
                    // Cliente - Solo nombres completos
                    'María Elena García López',
                    'DNI',
                    '12345678',
                    '987654321',
                    'maria.garcia@email.com',
                    
                    // Lote
                    '15',
                    'A',
                    
                    // Venta y Control
                    '2024-01-15',
                    'RESERVA',
                    'Cliente preferencial',
                    'ACTIVO'
                ],
                [
                    // Asesor
                    'Ana Sofía Rodríguez',
                    'ASE002',
                    'ana.rodriguez@casabonita.com',
                    
                    // Cliente - Solo nombres completos
                    'Carlos Alberto Mendoza Silva',
                    'DNI',
                    '87654321',
                    '998877665',
                    'carlos.mendoza@email.com',
                    
                    // Lote
                    '22',
                    'B',
                    
                    // Venta y Control
                    '2024-01-22',
                    'CONTRATO',
                    'Cliente nuevo',
                    'ACTIVO'
                ]
            ];

            // Crear el archivo Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Plantilla Simplificada');

            // Configurar encabezados
            $sheet->fromArray($headers, null, 'A1');
            
            // Aplicar estilos a los encabezados
            $headerRange = 'A1:' . $sheet->getHighestColumn() . '1';
            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);

            // Agregar datos de ejemplo
            $sheet->fromArray($exampleData, null, 'A2');
            
            // Ajustar ancho de columnas
            $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
            for ($i = 1; $i <= $highestColumnIndex; $i++) {
                $column = Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Agregar comentarios explicativos
            $sheet->getComment('A1')->getText()->createTextRun(
                "PLANTILLA SIMPLIFICADA DE IMPORTACIÓN DE CONTRATOS\n\n" .
                "Esta plantilla contiene solo 14 campos esenciales.\n" .
                "Los datos financieros se obtienen automáticamente\n" .
                "de las Plantillas Financieras de Lotes configuradas.\n\n" .
                "TIPO_OPERACION: RESERVA o CONTRATO\n" .
                "ESTADO_CONTRATO: ACTIVO, INACTIVO, CANCELADO"
            );

            // Crear el archivo temporal
            $writer = new Xlsx($spreadsheet);
            $filename = 'plantilla_simplificada_contratos_' . date('Y-m-d_H-i-s') . '.xlsx';
            $tempPath = storage_path('app/temp/' . $filename);
            
            // Asegurar que el directorio existe
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            
            $writer->save($tempPath);

            // Retornar el archivo para descarga
            return response()->download($tempPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (Exception $e) {
            Log::error('Error al generar plantilla simplificada de contratos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar la plantilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar plantilla integral de ejemplo para importación
     */
    public function downloadTemplate()
    {
        try {
            // Template ultra simplificado con solo 14 campos esenciales
            $headers = [
                // Sección Asesor (3 campos)
                'ASESOR_NOMBRE',
                'ASESOR_CODIGO',
                'ASESOR_EMAIL',
                
                // Sección Cliente (5 campos)
                'CLIENTE_NOMBRE_COMPLETO',
                'CLIENTE_TIPO_DOCUMENTO',
                'CLIENTE_NUMERO_DOCUMENTO',
                'CLIENTE_TELEFONO_1',
                'CLIENTE_EMAIL',
                
                // Sección Lote (2 campos)
                'LOTE_NUMERO',
                'LOTE_MANZANA',
                
                // Sección Venta/Contrato (4 campos)
                'FECHA_VENTA', 'TIPO_OPERACION', 'OBSERVACIONES', 'CONTRATO_ESTADO'
            ];

            // Datos de ejemplo ultra simplificados con solo 14 campos
            $exampleData = [
                [
                    // Asesor (3 campos)
                    'Juan Carlos Pérez Mendoza',
                    'ASE001',
                    'juan.perez@casabonita.com',
                    
                    // Cliente (5 campos)
                    'María Elena García López',
                    'DNI',
                    '12345678',
                    '987654321',
                    'maria.garcia@email.com',
                    
                    // Lote (2 campos)
                    '15',
                    'A',
                    
                    // Venta/Contrato (4 campos)
                     '2024-01-15',
                     'RESERVA',
                     'Cliente preferencial',
                     'ACTIVO'
                ],
                [
                    // Asesor (3 campos)
                    'Ana Sofía Rodríguez Torres',
                    'ASE002',
                    'ana.rodriguez@casabonita.com',
                    
                    // Cliente (5 campos)
                    'Carlos Alberto Mendoza Silva',
                    'DNI',
                    '87654321',
                    '998877665',
                    'carlos.mendoza@email.com',
                    
                    // Lote (2 campos)
                    '22',
                    'B',
                    
                    // Venta/Contrato (4 campos)
                     '2024-01-22',
                     'CONTRATO',
                     'Cliente nuevo',
                     'ACTIVO'
                ]
            ];

            // Crear nuevo spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Plantilla Contratos');

            // Definir colores por sección (plantilla ultra simplificada)
            $sectionColors = [
                'asesor' => '4472C4',      // Azul
                'cliente' => 'FFC000',     // Amarillo
                'lote' => '7030A0',        // Morado
                'venta' => '70AD47'        // Verde
            ];

            // Mapeo de rangos de columnas por sección (14 campos)
             $sectionRanges = [
                 'asesor' => ['start' => 1, 'end' => 3],
                 'cliente' => ['start' => 4, 'end' => 8],
                 'lote' => ['start' => 9, 'end' => 10],
                 'venta' => ['start' => 11, 'end' => 14]
             ];

            // Agregar headers
            $columnIndex = 1;
            foreach ($headers as $header) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex) . '1', $header);
                $columnIndex++;
            }

            // Aplicar estilos por sección
            foreach ($sectionRanges as $section => $range) {
                $startCol = Coordinate::stringFromColumnIndex($range['start']);
                $endCol = Coordinate::stringFromColumnIndex($range['end']);
                $headerRange = $startCol . '1:' . $endCol . '1';
                
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                        'size' => 10
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $sectionColors[$section]]
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true
                    ]
                ]);
            }

            // Agregar datos de ejemplo
            $rowIndex = 2;
            foreach ($exampleData as $row) {
                $columnIndex = 1;
                foreach ($row as $value) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex, $value);
                    $columnIndex++;
                }
                $rowIndex++;
            }

            // Aplicar bordes a los datos
            $dataRange = 'A2:' . Coordinate::stringFromColumnIndex(count($headers)) . ($rowIndex - 1);
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ]
            ]);

            // Auto-ajustar ancho de columnas
            for ($i = 1; $i <= count($headers); $i++) {
                $column = Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($column)->setAutoSize(true);
                $sheet->getColumnDimension($column)->setWidth(max(12, $sheet->getColumnDimension($column)->getWidth()));
            }

            // Crear hoja de instrucciones detallada
            $instructionsSheet = $spreadsheet->createSheet();
            $instructionsSheet->setTitle('Instrucciones Detalladas');
            
            $instructions = [
                ['SECCIÓN', 'CAMPO', 'DESCRIPCIÓN', 'EJEMPLO', 'REQUERIDO'],
                
                // Sección Asesor (3 campos)
                ['ASESOR', 'ASESOR_NOMBRE', 'Nombre completo del asesor de ventas', 'Juan Carlos Pérez Mendoza', 'SÍ'],
                ['ASESOR', 'ASESOR_CODIGO', 'Código único del empleado asesor', 'ASE001', 'NO'],
                ['ASESOR', 'ASESOR_EMAIL', 'Email corporativo del asesor', 'juan.perez@casabonita.com', 'NO'],
                
                // Sección Cliente (5 campos)
                ['CLIENTE', 'CLIENTE_NOMBRE_COMPLETO', 'Nombre completo del cliente (las 2 últimas palabras son apellidos)', 'María Elena García López', 'SÍ'],
                ['CLIENTE', 'CLIENTE_TIPO_DOCUMENTO', 'Tipo de documento', 'DNI, CE, Pasaporte', 'NO'],
                ['CLIENTE', 'CLIENTE_NUMERO_DOCUMENTO', 'Número de documento', '12345678', 'NO'],
                ['CLIENTE', 'CLIENTE_TELEFONO_1', 'Teléfono principal', '987654321', 'NO'],
                ['CLIENTE', 'CLIENTE_EMAIL', 'Email del cliente', 'maria.garcia@email.com', 'NO'],
                
                // Sección Lote (2 campos)
                ['LOTE', 'LOTE_NUMERO', 'Número del lote', '15', 'SÍ'],
                ['LOTE', 'LOTE_MANZANA', 'Manzana donde está ubicado', 'A', 'NO'],
                
                // Sección Venta/Contrato (4 campos)
                 ['VENTA', 'FECHA_VENTA', 'Fecha de la venta (YYYY-MM-DD)', '2024-01-15', 'SÍ'],
                 ['VENTA', 'TIPO_OPERACION', 'Tipo de operación', 'RESERVA, CONTRATO', 'SÍ'],
                 ['VENTA', 'OBSERVACIONES', 'Observaciones adicionales', 'Cliente preferencial', 'NO'],
                 ['VENTA', 'CONTRATO_ESTADO', 'Estado del contrato', 'ACTIVO, PENDIENTE, CANCELADO', 'NO']
            ];

            $rowIndex = 1;
            foreach ($instructions as $instruction) {
                $colIndex = 1;
                foreach ($instruction as $value) {
                    $instructionsSheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $rowIndex, $value);
                    $colIndex++;
                }
                $rowIndex++;
            }

            // Estilo para headers de instrucciones
            $instructionsSheet->getStyle('A1:E1')->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2F5597']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN
                    ]
                ]
            ]);

            // Aplicar colores por sección en instrucciones
            $currentSection = '';
            for ($row = 2; $row <= $rowIndex - 1; $row++) {
                $section = $instructionsSheet->getCell('A' . $row)->getValue();
                if ($section !== $currentSection) {
                    $currentSection = $section;
                    $color = match($section) {
                        'ASESOR' => 'E8F1FF',
                        'CLIENTE' => 'FFF8E1',
                        'LOTE' => 'F3E8FF',
                        'VENTA' => 'E8F5E8',
                        default => 'FFFFFF'
                    };
                    
                    $instructionsSheet->getStyle('A' . $row . ':E' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $color]
                        ]
                    ]);
                }
            }

            // Auto-ajustar columnas de instrucciones
            foreach (['A', 'B', 'C', 'D', 'E'] as $column) {
                $instructionsSheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Activar la primera hoja
            $spreadsheet->setActiveSheetIndex(0);

            // Generar archivo Excel
            $writer = new Xlsx($spreadsheet);
            $fileName = 'plantilla_importacion_simplificada_' . date('Y-m-d_H-i-s') . '.xlsx';
            $tempPath = storage_path('app/temp/' . $fileName);

            // Crear directorio si no existe
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            $writer->save($tempPath);

            // Headers para evitar cache
            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ];

            // Retornar archivo para descarga
            return response()->download($tempPath, $fileName, $headers)->deleteFileAfterSend(true);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar plantilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de importaciones
     */
    public function getImportHistory(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $status = $request->get('status');
            $userId = $request->get('user_id');
            
            $query = ContractImportLog::with('user:id,name,email')
                ->orderBy('created_at', 'desc');
            
            if ($status) {
                $query->byStatus($status);
            }
            
            if ($userId) {
                $query->where('user_id', $userId);
            }
            
            $imports = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'message' => 'Historial obtenido exitosamente',
                'data' => $imports,
                'filters' => [
                    'available_statuses' => ContractImportLog::getStatuses()
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de importaciones
     */
    public function getImportStats(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 30);
            
            // Estadísticas de importaciones
            $importStats = ContractImportLog::getImportStats($days);
            
            // Estadísticas básicas de contratos y reservaciones
            $totalContracts = \Modules\Sales\Models\Contract::count();
            $totalReservations = \Modules\Sales\Models\Reservation::count();
            $activeContracts = \Modules\Sales\Models\Contract::where('status', 'vigente')->count();
            $completedReservations = \Modules\Sales\Models\Reservation::where('status', 'completada')->count();
            
            // Estadísticas por mes (últimos 6 meses)
            $monthlyStats = ContractImportLog::selectRaw('
                    DATE_FORMAT(created_at, "%Y-%m") as month,
                    COUNT(*) as total_imports,
                    SUM(success_count) as total_success,
                    SUM(error_count) as total_errors
                ')
                ->where('created_at', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Estadísticas obtenidas exitosamente',
                'data' => [
                    'import_stats' => $importStats,
                    'sales_stats' => [
                        'total_contracts' => $totalContracts,
                        'total_reservations' => $totalReservations,
                        'active_contracts' => $activeContracts,
                        'completed_reservations' => $completedReservations
                    ],
                    'monthly_stats' => $monthlyStats,
                    'period_days' => $days,
                    'last_updated' => now()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }
}