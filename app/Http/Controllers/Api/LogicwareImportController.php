<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LogicwareContractImporter;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogicwareImportController extends Controller
{
    protected $importer;

    public function __construct(LogicwareContractImporter $importer)
    {
        $this->importer = $importer;
    }

    /**
     * Importar contratos desde Logicware
     * 
     * POST /api/logicware/import-contracts
     * 
     * Body (opcional):
     * {
     *   "start_date": "2025-11-01",
     *   "end_date": "2025-11-17",
     *   "force_refresh": false
     * }
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function importContracts(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
                'force_refresh' => 'nullable|boolean'
            ]);

            $startDate = $validated['start_date'] ?? null;
            $endDate = $validated['end_date'] ?? null;
            $forceRefresh = $validated['force_refresh'] ?? false;

            Log::info('[LogicwareImportAPI] Solicitud de importación', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'force_refresh' => $forceRefresh,
                'user_id' => auth()->id() ?? 'N/A'
            ]);

            // Ejecutar importación
            $results = $this->importer->importContracts($startDate, $endDate, $forceRefresh);

            return response()->json([
                'success' => true,
                'message' => 'Importación completada',
                'data' => $results
            ], 200);

        } catch (Exception $e) {
            Log::error('[LogicwareImportAPI] Error en importación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error en la importación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estado de la integración con Logicware
     * 
     * GET /api/logicware/status
     * 
     * @return JsonResponse
     */
    public function getStatus(): JsonResponse
    {
        try {
            $logicwareService = app(\App\Services\LogicwareApiService::class);

            $dailyRequests = $logicwareService->getDailyRequestCount();
            $hasAvailableRequests = $logicwareService->hasAvailableRequests();

            return response()->json([
                'success' => true,
                'data' => [
                    'api_enabled' => !empty(config('services.logicware.api_key')),
                    'daily_requests_used' => $dailyRequests,
                    'daily_requests_limit' => 4,
                    'requests_available' => $hasAvailableRequests,
                    'cached_data_available' => cache()->has("logicware_sales_" . config('services.logicware.subdomain') . "_" . now()->startOfMonth()->toDateString() . "_" . now()->endOfMonth()->toDateString())
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('[LogicwareImportAPI] Error obteniendo estado', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo estado de Logicware',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renovar Bearer Token de Logicware manualmente
     * 
     * POST /api/logicware/renew-token
     * 
     * @return JsonResponse
     */
    public function renewToken(): JsonResponse
    {
        try {
            $logicwareService = app(\App\Services\LogicwareApiService::class);

            Log::info('[LogicwareImportAPI] Renovación manual de token solicitada', [
                'user_id' => auth()->id() ?? 'N/A'
            ]);

            // Forzar renovación del token
            $token = $logicwareService->generateToken(true);

            return response()->json([
                'success' => true,
                'message' => 'Token renovado exitosamente',
                'data' => [
                    'token_preview' => substr($token, 0, 50) . '...',
                    'valid_until' => now()->addHours(23)->format('Y-m-d H:i:s'),
                    'renewed_at' => now()->format('Y-m-d H:i:s')
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('[LogicwareImportAPI] Error renovando token', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al renovar token de Logicware',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener información del token actual
     * 
     * GET /api/logicware/token-info
     * 
     * @return JsonResponse
     */
    public function getTokenInfo(): JsonResponse
    {
        try {
            $subdomain = config('services.logicware.subdomain', 'casabonita');
            $cacheKey = "logicware_bearer_token_{$subdomain}";
            
            $hasToken = cache()->has($cacheKey);
            
            if ($hasToken) {
                $token = cache()->get($cacheKey);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'has_token' => true,
                        'token_preview' => substr($token, 0, 50) . '...',
                        'cache_key' => $cacheKey,
                        'message' => 'Token activo en caché'
                    ]
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'has_token' => false,
                    'message' => 'No hay token en caché. Se generará automáticamente en la próxima petición.'
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('[LogicwareImportAPI] Error obteniendo info del token', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información del token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener stock completo con TODOS los datos de Logicware
     * 
     * GET /api/logicware/full-stock
     * 
     * Query params (opcional):
     * - force_refresh: boolean (default: false) - Forzar consulta al API
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getFullStock(Request $request): JsonResponse
    {
        try {
            $logicwareService = app(\App\Services\LogicwareApiService::class);

            $forceRefresh = $request->query('force_refresh', false);
            $forceRefresh = filter_var($forceRefresh, FILTER_VALIDATE_BOOLEAN);

            Log::info('[LogicwareImportAPI] Solicitud de stock completo', [
                'user_id' => auth()->id() ?? 'N/A',
                'force_refresh' => $forceRefresh,
                'daily_requests_used' => $logicwareService->getDailyRequestCount()
            ]);

            // Obtener stock completo
            $stockData = $logicwareService->getFullStockData($forceRefresh);

            $totalUnits = isset($stockData['data']) ? count($stockData['data']) : 0;

            // Analizar datos para estadísticas
            $stats = [
                'total_units' => $totalUnits,
                'by_status' => [],
                'with_seller' => 0,
                'with_client' => 0,
                'with_reservation' => 0,
                'data_source' => isset($stockData['cached_at']) ? 'cache' : 'api'
            ];

            if (isset($stockData['data']) && is_array($stockData['data'])) {
                foreach ($stockData['data'] as $unit) {
                    // Contar por estado
                    $status = $unit['status'] ?? 'unknown';
                    $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + 1;

                    // Contar unidades con vendedor
                    if (!empty($unit['seller']) || !empty($unit['sellerName'])) {
                        $stats['with_seller']++;
                    }

                    // Contar unidades con cliente
                    if (!empty($unit['client']) || !empty($unit['clientName'])) {
                        $stats['with_client']++;
                    }

                    // Contar unidades con reserva
                    if (!empty($unit['reservation']) || !empty($unit['reservationDate'])) {
                        $stats['with_reservation']++;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Stock completo obtenido exitosamente',
                'data' => $stockData,
                'stats' => $stats,
                'cache_info' => [
                    'cached_at' => $stockData['cached_at'] ?? null,
                    'cache_expires_at' => $stockData['cache_expires_at'] ?? null,
                    'is_cached' => isset($stockData['cached_at'])
                ],
                'api_usage' => [
                    'daily_requests_used' => $logicwareService->getDailyRequestCount(),
                    'daily_requests_limit' => 4,
                    'requests_remaining' => 4 - $logicwareService->getDailyRequestCount()
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('[LogicwareImportAPI] Error obteniendo stock completo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener stock completo de Logicware',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
