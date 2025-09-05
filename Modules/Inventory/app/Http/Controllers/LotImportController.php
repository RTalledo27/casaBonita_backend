<?php

namespace Modules\Inventory\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Inventory\Services\LotImportService;
use Modules\Inventory\Http\Requests\LotImportRequest;
use Modules\Inventory\Models\LotImportLog;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LotImportController extends Controller
{
    protected LotImportService $lotImportService;

    public function __construct(LotImportService $lotImportService)
    {
        $this->lotImportService = $lotImportService;
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240'
        ]);

        try {
            $file = $request->file('file');
            $results = $this->lotImportService->processExcel($file);

            return response()->json([
                'success' => true,
                'message' => 'Archivo procesado exitosamente',
                'data' => $results
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valida la estructura del archivo Excel antes de importar
     */
    public function validateFile(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $validation = $this->lotImportService->validateExcelStructure($request->file('file'));
            
            return response()->json([
                'success' => true,
                'message' => 'Validación completada',
                'data' => $validation
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al validar el archivo: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene estadísticas de importación
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->lotImportService->getImportStatistics();
            
            return response()->json([
                'success' => true,
                'message' => 'Estadísticas obtenidas exitosamente',
                'data' => $statistics
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Diagnóstico de templates financieros de lotes
     */
    public function diagnoseLotFinancialTemplates(): JsonResponse
    {
        try {
            $diagnostic = $this->lotImportService->diagnoseLotFinancialTemplates();
            
            return response()->json([
                'success' => true,
                'message' => 'Diagnóstico completado exitosamente',
                'data' => $diagnostic
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar diagnóstico: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descarga el template de Excel para importación de lotes
     */
    public function downloadTemplate(): StreamedResponse
    {
        try {
            // Crear nuevo spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Definir headers (incluyendo nueva columna ESTADO)
            $headers = [
                'MZNA', 'LOTE', 'ÁREA LOTE', 'UBICACIÓN', 'PRECIO m2', 
                'PRECIO LISTA', 'DSCTO', 'PRECIO VENTA', 'CUOTA BALON', 
                'BONO BPP', 'CUOTA INICIAL', 'CI FRACC', 'ESTADO',
                'A', 'B', 'E', 'F', 'G', 'H', 'I', 'D'
            ];
            
            // Agregar headers en la primera fila
            $sheet->fromArray($headers, null, 'A1');
            
            // Agregar segunda fila con valores de financiamiento (incluyendo nueva manzana B)
            $financingRow = [
                '', '', '', '', '', '', '', '', '', '', '', '', '',
                'CONTADO', '36', '24', '40', '40', '44', '55', '24'
            ];
            $sheet->fromArray($financingRow, null, 'A2');
            
            // Agregar fila de ejemplo (incluyendo valor para nueva manzana B)
            $exampleRow = [
                'A', '1', '96.00', 'BULEVAR', '392.70', '37688.00', 
                '7000.00', '30688.00', '3000.00', '3000.00', 
                '3000.00', '500.00', 'disponible', '30688.00', '852.44', '731.75', '757.14', 
                '692.34', '923.93', '456.40', '928.10'
            ];
            $sheet->fromArray($exampleRow, null, 'A3');
            
            // Agregar instrucciones en filas separadas
            $instructions = [
                'INSTRUCCIONES:',
                'Las columnas con letras (A, B, E, F, G, H, I, D, etc.) representan manzanas y sus opciones de financiamiento',
                'Los valores en la fila 2 definen el tipo: CONTADO = pago único, número = cuotas mensuales',
                'Los valores en las filas de datos son los montos específicos para cada manzana',
                'El sistema detecta automáticamente cualquier nueva manzana (letra A-Z) que agregues',
                'MZNA se crea automáticamente si no existe en el sistema',
                'UBICACIÓN se crea automáticamente si no existe como tipo de calle',
                'ESTADO: disponible, reservado, vendido, cancelado (si no se especifica, será "disponible")',
                'Todos los precios deben estar en formato numérico (sin comas)',
                'Ejemplo actual: A=CONTADO, B=36 cuotas, E=24 cuotas, F=40 cuotas, etc.',
                'Para agregar nueva manzana: agrega columna con letra y define financiamiento en fila 2'
            ];
            
            $row = 5;
            foreach ($instructions as $instruction) {
                $sheet->setCellValue('A' . $row, $instruction);
                $row++;
            }
            
            // Configurar el ancho de las columnas
            foreach (range('A', 'S') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            
            // Crear el writer
            $writer = new Xlsx($spreadsheet);
            
            // Configurar la respuesta para descarga
            $fileName = 'plantilla_importacion_lotes.xlsx';
            
            return new StreamedResponse(
                function () use ($writer) {
                    $writer->save('php://output');
                },
                200,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                    'Cache-Control' => 'max-age=0',
                ]
            );
        } catch (Exception $e) {
            // En caso de error, devolver respuesta de error
            return response('Error al generar template: ' . $e->getMessage(), 500)
                ->header('Content-Type', 'text/plain');
        }
    }

    /**
     * Obtiene el historial de importaciones de lotes
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $status = $request->get('status');
            $userId = $request->get('user_id');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            $query = LotImportLog::with('user:user_id,first_name,last_name,email')
                ->orderBy('created_at', 'desc');

            // Filtros opcionales
            if ($status) {
                $query->where('status', $status);
            }

            if ($userId) {
                $query->where('user_id', $userId);
            }

            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            $imports = $query->paginate($perPage);

            // Transformar los datos para incluir información adicional
            $imports->getCollection()->transform(function ($import) {
                return [
                    'import_log_id' => $import->import_log_id,
                    'user' => $import->user ? [
                        'id' => $import->user->id,
                        'name' => $import->user->first_name . ' ' . $import->user->last_name,
                        'email' => $import->user->email
                    ] : null,
                    'file_name' => $import->file_name,
                    'file_size' => $import->file_size,
                    'formatted_file_size' => $import->formatted_file_size,
                    'status' => $import->status,
                    'status_label' => $import->status_label,
                    'message' => $import->message,
                    'total_rows' => $import->total_rows,
                    'processed_rows' => $import->processed_rows,
                    'success_count' => $import->success_count,
                    'error_count' => $import->error_count,
                    'success_rate' => $import->success_rate,
                    'error_details' => $import->error_details,
                    'processing_time' => $import->processing_time,
                    'formatted_processing_time' => $import->formatted_processing_time,
                    'is_successful' => $import->is_successful,
                    'is_in_progress' => $import->is_in_progress,
                    'started_at' => $import->started_at,
                    'completed_at' => $import->completed_at,
                    'created_at' => $import->created_at,
                    'updated_at' => $import->updated_at
                ];
            });

            // Obtener estadísticas adicionales
            $stats = LotImportLog::getImportStats();

            return response()->json([
                'success' => true,
                'message' => 'Historial obtenido exitosamente',
                'data' => [
                    'imports' => $imports,
                    'statistics' => $stats,
                    'available_statuses' => LotImportLog::getStatuses()
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene las reglas de financiamiento por manzana
     */
    public function getFinancingRules(): JsonResponse
    {
        try {
            $rules = \Modules\Inventory\Models\ManzanaFinancingRule::with('manzana')
                ->get()
                ->map(function ($rule) {
                    return [
                        'manzana' => $rule->manzana->name,
                        'financing_type' => $rule->financing_type,
                        'max_installments' => $rule->max_installments,
                        'allows_balloon_payment' => $rule->allows_balloon_payment,
                        'allows_bpp_bonus' => $rule->allows_bpp_bonus,
                        'available_options' => $rule->getAvailableInstallmentOptions()
                    ];
                });
            
            return response()->json([
                'success' => true,
                'message' => 'Reglas de financiamiento obtenidas exitosamente',
                'data' => $rules
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener reglas de financiamiento: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene los lotes con sus templates financieros
     */
    public function getLotsWithFinancialData(Request $request): JsonResponse
    {
        try {
            $query = \Modules\Inventory\Models\Lot::with([
                'manzana', 
                'streetType', 
                'lotFinancialTemplate'
            ]);

            // Filtros opcionales
            if ($request->has('manzana')) {
                $query->whereHas('manzana', function ($q) use ($request) {
                    $q->where('name', $request->manzana);
                });
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('min_price') && $request->has('max_price')) {
                $query->whereBetween('total_price', [$request->min_price, $request->max_price]);
            }

            $lots = $query->paginate($request->get('per_page', 15));
            
            return response()->json([
                'success' => true,
                'message' => 'Lotes obtenidos exitosamente',
                'data' => $lots
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener lotes: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina todos los datos de importación (solo para desarrollo/testing)
     */
    public function clearImportData(): JsonResponse
    {
        try {
            // Solo permitir en entorno de desarrollo
            if (config('app.env') !== 'local') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta operación solo está disponible en entorno de desarrollo'
                ], 403);
            }

            \Modules\Inventory\Models\LotFinancialTemplate::truncate();
            \Modules\Inventory\Models\ManzanaFinancingRule::truncate();
            
            return response()->json([
                'success' => true,
                'message' => 'Datos de importación eliminados exitosamente'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar datos: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Inicia una importación asíncrona de lotes
     */
    public function asyncImport(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240'
        ]);

        try {
            $file = $request->file('file');
            $user = auth()->user();
            
            // Generar nombre único para el archivo
            $fileName = time() . '_' . $file->getClientOriginalName();
            
            // Guardar archivo temporalmente
            $filePath = $file->storeAs('temp/lot-imports', $fileName, 'local');
            
            // Crear registro del proceso asíncrono
            $importProcess = \App\Models\AsyncImportProcess::create([
                'type' => 'lot_import',
                'status' => 'pending',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'user_id' => $user->id,
            ]);
            
            // Despachar el job para procesamiento en background
            \App\Jobs\ProcessLotImportJob::dispatch($importProcess, [
                'validate_only' => $request->boolean('validate_only', false),
                'skip_duplicates' => $request->boolean('skip_duplicates', true),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Importación asíncrona iniciada exitosamente',
                'data' => [
                    'process_id' => $importProcess->id,
                    'status' => $importProcess->status,
                    'file_name' => $importProcess->file_name,
                    'created_at' => $importProcess->created_at,
                    'status_url' => url('/api/v1/inventory/lot-import/async/' . $importProcess->id . '/status')
                ]
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar importación asíncrona: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el estado de una importación asíncrona
     */
    public function getAsyncStatus(int $id): JsonResponse
    {
        try {
            $importProcess = \App\Models\AsyncImportProcess::findOrFail($id);
            
            // Verificar que el usuario tenga acceso al proceso
            if ($importProcess->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para acceder a este proceso'
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Estado obtenido exitosamente',
                'data' => [
                    'id' => $importProcess->id,
                    'type' => $importProcess->type,
                    'status' => $importProcess->status,
                    'file_name' => $importProcess->file_name,
                    'total_rows' => $importProcess->total_rows,
                    'processed_rows' => $importProcess->processed_rows,
                    'successful_rows' => $importProcess->successful_rows,
                    'failed_rows' => $importProcess->failed_rows,
                    'progress_percentage' => $importProcess->progress_percentage,
                    'errors' => $importProcess->errors,
                    'warnings' => $importProcess->warnings,
                    'summary' => $importProcess->summary,
                    'started_at' => $importProcess->started_at,
                    'completed_at' => $importProcess->completed_at,
                    'created_at' => $importProcess->created_at,
                    'updated_at' => $importProcess->updated_at,
                    'is_completed' => $importProcess->isCompleted(),
                    'is_in_progress' => $importProcess->isInProgress(),
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Proceso de importación no encontrado'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estado: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}