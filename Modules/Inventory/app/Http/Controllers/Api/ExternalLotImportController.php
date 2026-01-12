<?php

namespace Modules\Inventory\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Inventory\Services\ExternalLotImportService;
use App\Services\LogicwareApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\AsyncImportProcess;
use App\Jobs\ProcessSalesImportJob;

/**
 * Controlador para importación de lotes desde API externa
 */
class ExternalLotImportController extends Controller
{
    protected ExternalLotImportService $importService;
    protected LogicwareApiService $apiService;

    public function __construct(
        ExternalLotImportService $importService,
        LogicwareApiService $apiService
    ) {
        $this->importService = $importService;
        $this->apiService = $apiService;
    }

    /**
     * Probar conexión con el API externa
     * USA CACHÉ - No consume consultas del límite diario
     * 
     * GET /api/inventory/external-lot-import/test-connection
     */
    public function testConnection(): JsonResponse
    {
        try {
            // Usar caché (NO consume consulta diaria)
            $properties = $this->apiService->getProperties(['limit' => 5], false);
            
            return response()->json([
                'success' => true,
                'message' => 'Conexión exitosa con API externa (datos desde caché)',
                'data' => [
                    'connected' => true,
                    'sample_count' => isset($properties['data']) ? count($properties['data']) : 0,
                    'sample_properties' => array_slice($properties['data'] ?? [], 0, 3),
                    'daily_requests_used' => $this->apiService->getDailyRequestCount(),
                    'daily_requests_remaining' => 4 - $this->apiService->getDailyRequestCount(),
                    'cached_at' => $properties['cached_at'] ?? null,
                    'cache_expires_at' => $properties['cache_expires_at'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('[ExternalLotImportController] Error en test de conexión', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_details' => [
                    'type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Importar todos los lotes disponibles
     * 
     * POST /api/inventory/external-lot-import/sync-all
     */
    public function syncAll(Request $request): JsonResponse
    {
        try {
            Log::info('[ExternalLotImportController] Iniciando sincronización completa');

            $result = $this->importService->importLots();

            $statusCode = $result['success'] ? 200 : 500;

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] 
                    ? 'Sincronización completada exitosamente' 
                    : 'Sincronización completada con errores',
                'data' => [
                    'stats' => $result['stats'],
                    'errors' => $result['errors']
                ]
            ], $statusCode);

        } catch (\Exception $e) {
            Log::error('[ExternalLotImportController] Error en sincronización completa', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error durante la sincronización: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sincronizar un lote específico por código
     * 
     * POST /api/inventory/external-lot-import/sync-by-code
     * Body: { "code": "E2-02" }
     */
    public function syncByCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $code = $request->input('code');
            
            Log::info('[ExternalLotImportController] Sincronizando lote por código', [
                'code' => $code
            ]);

            $result = $this->importService->syncLotByCode($code);

            $statusCode = $result['success'] ? 200 : 500;

            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            Log::error('[ExternalLotImportController] Error sincronizando por código', [
                'code' => $request->input('code'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error sincronizando lote: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de la última importación
     * 
     * GET /api/inventory/external-lot-import/stats
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->importService->getStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('[ExternalLotImportController] Error obteniendo estadísticas', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener vista previa de lotes a importar sin realizar la importación
     * USA CACHÉ por defecto - Set force_refresh=1 para consultar API real
     * 
     * GET /api/inventory/external-lot-import/preview?force_refresh=1
     */
    public function preview(Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', 10);
            $forceRefresh = $request->boolean('force_refresh', false);
            
            Log::info('[ExternalLotImportController] Obteniendo vista previa', [
                'force_refresh' => $forceRefresh
            ]);

            $properties = $this->apiService->getAvailableProperties($forceRefresh);
            
            $preview = [];
            if (isset($properties['data'])) {
                foreach (array_slice($properties['data'], 0, $limit) as $property) {
                    $preview[] = [
                        'external_id' => $property['id'] ?? null,
                        'code' => $property['code'] ?? 'N/A',
                        'status' => $property['status'] ?? 'N/A',
                        'area' => $property['area'] ?? 'N/A',
                        'price' => $property['price'] ?? 'N/A',
                        'currency' => $property['currency'] ?? 'N/A'
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_available' => isset($properties['data']) ? count($properties['data']) : 0,
                    'preview_count' => count($preview),
                    'properties' => $preview,
                    'daily_requests_used' => $this->apiService->getDailyRequestCount(),
                    'daily_requests_remaining' => 4 - $this->apiService->getDailyRequestCount(),
                    'from_cache' => !$forceRefresh,
                    'cached_at' => $properties['cached_at'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('[ExternalLotImportController] Error obteniendo preview', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo vista previa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener ventas de clientes desde LOGICWARE (usa caché por defecto)
     * 
     * GET /api/inventory/external-lot-import/sales?startDate=YYYY-MM-DD&endDate=YYYY-MM-DD&force_refresh=1
     */
    public function sales(Request $request): JsonResponse
    {
        try {
            $startDate = $request->input('startDate');
            $endDate = $request->input('endDate');
            $forceRefresh = $request->boolean('force_refresh', false);

            Log::info('[ExternalLotImportController] Obteniendo ventas', [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'force_refresh' => $forceRefresh
            ]);

            // Validar fechas
            if ($startDate && !strtotime($startDate)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fecha de inicio inválida'
                ], 400);
            }

            if ($endDate && !strtotime($endDate)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fecha de fin inválida'
                ], 400);
            }

            $sales = $this->apiService->getSales($startDate, $endDate, $forceRefresh);

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => isset($sales['data']) ? count($sales['data']) : 0,
                    'items' => $sales['data'] ?? [],
                    'cached_at' => $sales['cached_at'] ?? null,
                    'cache_expires_at' => $sales['cache_expires_at'] ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('[ExternalLotImportController] Error obteniendo ventas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo ventas: ' . $e->getMessage(),
                'error_details' => config('app.debug') ? [
                    'type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }

    /**
     * Importar ventas/contratos desde LOGICWARE al sistema local
     * POST /api/v1/inventory/external-lot-import/sales/import
     * Body: { startDate, endDate, force_refresh }
     */
    public function importSales(Request $request): JsonResponse
    {
        try {
            $startDate = $request->input('startDate');
            $endDate = $request->input('endDate');
            $forceRefresh = $request->boolean('force_refresh', false);
            $async = $request->boolean('async', true);

            Log::info('[ExternalLotImportController] Importando ventas', [
                'start' => $startDate, 
                'end' => $endDate, 
                'force' => $forceRefresh,
                'async' => $async,
            ]);

            // Validar fechas
            if ($startDate && !strtotime($startDate)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fecha de inicio inválida'
                ], 400);
            }

            if ($endDate && !strtotime($endDate)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fecha de fin inválida'
                ], 400);
            }

            if ($async) {
                $user = $request->user();
                $userId = $user?->user_id ?? $user?->id;

                $importProcess = AsyncImportProcess::create([
                    'type' => 'logicware_sales_import',
                    'status' => 'pending',
                    'file_name' => 'logicware_sales_import',
                    'file_path' => 'N/A',
                    'user_id' => $userId,
                    'summary' => [
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                        'force_refresh' => $forceRefresh,
                    ],
                ]);

                ProcessSalesImportJob::dispatch($importProcess, [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'force_refresh' => $forceRefresh,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Importación iniciada. Procesando en segundo plano.',
                    'data' => [
                        'process_id' => $importProcess->id,
                        'status' => $importProcess->status,
                        'status_url' => url('/api/v1/inventory/external-lot-import/sales/import/async/' . $importProcess->id . '/status'),
                    ],
                ], 202);
            }

            // Modo síncrono (solo si async=false)
            set_time_limit(600);
            ini_set('max_execution_time', '600');
            ini_set('memory_limit', '512M');

            $result = $this->importService->importSales($startDate, $endDate, $forceRefresh);

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('[ExternalLotImportController] Error importando ventas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error en la importación: ' . $e->getMessage(),
                'error_details' => config('app.debug') ? [
                    'type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }

    /**
     * Estado de importación asíncrona de ventas
     * GET /api/v1/inventory/external-lot-import/sales/import/async/{id}/status
     */
    public function getSalesImportStatus(int $id): JsonResponse
    {
        try {
            $importProcess = AsyncImportProcess::findOrFail($id);

            $user = request()->user();
            $userId = $user?->user_id ?? $user?->id;
            if ($importProcess->user_id && $userId && (int) $importProcess->user_id !== (int) $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para acceder a este proceso'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $importProcess->id,
                    'type' => $importProcess->type,
                    'status' => $importProcess->status,
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
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Proceso de importación no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refrescar token de autenticación
     * 
     * POST /api/inventory/external-lot-import/refresh-token
     */
    public function refreshToken(): JsonResponse
    {
        try {
            Log::info('[ExternalLotImportController] Refrescando token');

            $token = $this->apiService->refreshToken();

            return response()->json([
                'success' => true,
                'message' => 'Token refrescado exitosamente',
                'data' => [
                    'token_preview' => substr($token, 0, 20) . '...'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('[ExternalLotImportController] Error refrescando token', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error refrescando token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar caché de datos del API
     * ⚠️ Después de limpiar, la próxima consulta consumirá una request del límite diario
     * 
     * POST /api/inventory/external-lot-import/clear-cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            Log::info('[ExternalLotImportController] Limpiando caché');

            $this->apiService->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Caché limpiado exitosamente. La próxima consulta usará el API real.',
                'data' => [
                    'daily_requests_used' => $this->apiService->getDailyRequestCount(),
                    'daily_requests_remaining' => 4 - $this->apiService->getDailyRequestCount()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('[ExternalLotImportController] Error limpiando caché', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error limpiando caché: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener información sobre el uso del límite diario
     * 
     * GET /api/inventory/external-lot-import/daily-limit-status
     */
    public function getDailyLimitStatus(): JsonResponse
    {
        try {
            $used = $this->apiService->getDailyRequestCount();
            $remaining = 4 - $used;
            $hasAvailable = $this->apiService->hasAvailableRequests();

            return response()->json([
                'success' => true,
                'data' => [
                    'daily_limit' => 4,
                    'requests_used' => $used,
                    'requests_remaining' => $remaining,
                    'has_available_requests' => $hasAvailable,
                    'percentage_used' => ($used / 4) * 100,
                    'reset_at' => now()->endOfDay()->toDateTimeString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('[ExternalLotImportController] Error obteniendo status del límite', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo información del límite diario: ' . $e->getMessage()
            ], 500);
        }
    }
}
