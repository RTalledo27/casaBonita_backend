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

            // Normalizar cada unidad para que coincida con la interfaz del frontend
            $normalizedUnits = [];
            if (isset($stockData['data']) && is_array($stockData['data'])) {
                foreach ($stockData['data'] as $unit) {
                    $normalizedUnits[] = $this->normalizeUnit($unit);
                }
            }

            $totalUnits = count($normalizedUnits);

            // Analizar datos para estadísticas (usando datos normalizados)
            $stats = [
                'total_units' => $totalUnits,
                'by_status' => [],
                'with_seller' => 0,
                'with_advisor' => 0,
                'with_client' => 0,
                'with_reservation' => 0,
                'data_source' => isset($stockData['cached_at']) ? 'cache' : 'api'
            ];

            foreach ($normalizedUnits as $unit) {
                // Contar por estado (ya normalizado a lowercase)
                $status = $unit['status'] ?? 'unknown';
                $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + 1;

                // Contar unidades con asesor
                if (!empty($unit['advisor']['name'])) {
                    $stats['with_advisor']++;
                    $stats['with_seller']++;
                }

                // Contar unidades con cliente
                if (!empty($unit['client']['name'])) {
                    $stats['with_client']++;
                }

                // Contar unidades con reserva
                if (!empty($unit['reservation'])) {
                    $stats['with_reservation']++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Stock completo obtenido exitosamente',
                'data' => $normalizedUnits,
                'statistics' => $stats,
                'cache_info' => [
                    'cached_at' => $stockData['cached_at'] ?? null,
                    'cache_expires_at' => $stockData['cache_expires_at'] ?? null,
                    'is_cached' => isset($stockData['cached_at'])
                ],
                'api_info' => [
                    'daily_requests_used' => $logicwareService->getDailyRequestCount(),
                    'daily_requests_limit' => 4,
                    'has_available_requests' => $logicwareService->getDailyRequestCount() < 4
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

    /**
     * Normalizar una unidad del API de Logicware al formato esperado por el frontend
     * 
     * Mapea campos como: salableArea → area, totalPrice → price,
     * status "Vendido" → "vendido", etc.
     */
    private function normalizeUnit(array $unit): array
    {
        // Extraer block y lot del código (ej: "E-0013" → block: "E", lot: "13")
        $code = $unit['code'] ?? '';
        $block = '';
        $lot = '';
        if (preg_match('/^([A-Z]+\d*)[-_]?(\d+)$/i', $code, $matches)) {
            $block = strtoupper($matches[1]);
            $lot = ltrim($matches[2], '0') ?: '0'; // "0013" → "13"
        }

        // Normalizar estado
        $status = $this->normalizeStatus($unit['status'] ?? '', $unit['lockType'] ?? '');

        // Construir nombre legible
        $name = $unit['name'] ?? ($unit['remarks'] ?? "Lote {$code}");

        // Extraer info del asesor/vendedor si existe
        $advisor = null;
        if (!empty($unit['sellerName']) || !empty($unit['seller'])) {
            $advisor = [
                'id' => $unit['sellerId'] ?? null,
                'name' => $unit['sellerName'] ?? $unit['seller'] ?? null,
                'email' => $unit['sellerEmail'] ?? null,
                'phone' => $unit['sellerPhone'] ?? null,
            ];
        }

        // Extraer info del cliente si existe
        $client = null;
        if (!empty($unit['clientName']) || !empty($unit['client'])) {
            $clientData = is_array($unit['client'] ?? null) ? $unit['client'] : [];
            $client = [
                'id' => $unit['clientId'] ?? ($clientData['id'] ?? null),
                'name' => $unit['clientName'] ?? ($clientData['name'] ?? ($clientData['firstName'] ?? '') . ' ' . ($clientData['lastName'] ?? '')),
                'document' => $unit['clientDocument'] ?? ($clientData['documentNumber'] ?? null),
                'email' => $unit['clientEmail'] ?? ($clientData['email'] ?? null),
                'phone' => $unit['clientPhone'] ?? ($clientData['phone'] ?? null),
            ];
            // Limpiar nombre si está vacío
            if (trim($client['name']) === '') {
                $client = null;
            }
        }

        // Extraer info de reserva si existe
        $reservation = null;
        if (!empty($unit['reservationDate']) || !empty($unit['reservation'])) {
            $resData = is_array($unit['reservation'] ?? null) ? $unit['reservation'] : [];
            $reservation = [
                'id' => $resData['id'] ?? null,
                'date' => $unit['reservationDate'] ?? ($resData['date'] ?? null),
                'amount' => $unit['reservationAmount'] ?? ($resData['amount'] ?? null),
                'status' => $resData['status'] ?? null,
            ];
        }

        // Extraer info financiera
        $financial = null;
        if (!empty($unit['initialPayment']) || !empty($unit['monthlyPayment']) || !empty($unit['roofBonus'])) {
            $financial = [
                'initial_payment' => $unit['initialPayment'] ?? null,
                'monthly_payment' => $unit['monthlyPayment'] ?? null,
                'num_installments' => $unit['installments'] ?? null,
                'total_amount' => $unit['totalPrice'] ?? null,
                'down_payment_percentage' => $unit['downPaymentPercentage'] ?? null,
                'roof_bonus' => $unit['roofBonus'] ?? null,
            ];
        }

        return [
            'id' => $unit['id'] ?? null,
            'code' => $code,
            'name' => $name,
            'status' => $status,
            'block' => $block,
            'lot' => $lot,
            'area' => $unit['salableArea'] ?? ($unit['area'] ?? null),
            'price' => $unit['totalPrice'] ?? ($unit['price'] ?? 0),
            'price_per_sqm' => $unit['pricePerSqm'] ?? null,
            'model_name' => $unit['modelName'] ?? null,
            'stage_name' => $unit['stageName'] ?? null,
            'project_name' => $unit['projectName'] ?? null,
            'project_code' => $unit['projectCode'] ?? null,
            'type_name' => $unit['typeName'] ?? null,
            'is_corner' => $unit['isCorner'] ?? false,
            'front_length' => $unit['frontLength'] ?? null,
            'left_length' => $unit['leftLength'] ?? null,
            'right_length' => $unit['rightLength'] ?? null,
            'garden_area' => $unit['gardenArea'] ?? null,
            'terrace_area' => $unit['terraceArea'] ?? null,
            'orientation' => $unit['orientation'] ?? null,
            'remarks' => $unit['remarks'] ?? null,
            'lock_type' => $unit['lockType'] ?? null,
            'lock_description' => $unit['lockDescription'] ?? null,
            'roof_code' => $unit['roofCode'] ?? null,
            'roof_bonus' => $unit['roofBonus'] ?? null,
            'advisor' => $advisor,
            'client' => $client,
            'reservation' => $reservation,
            'financial' => $financial,
            'updated_at' => $unit['updatedAt'] ?? null,
        ];
    }

    /**
     * Normalizar el estado de Logicware al formato esperado por el frontend
     * 
     * Logicware devuelve: "Disponible", "Vendido", "Bloqueado", "No Vendible", "Separado", etc.
     * Frontend espera: "disponible", "reservado", "vendido"
     */
    private function normalizeStatus(string $rawStatus, string $lockType = ''): string
    {
        $status = mb_strtolower(trim($rawStatus));

        // Mapeo de estados de Logicware a estados del sistema
        $statusMap = [
            'disponible' => 'disponible',
            'vendido' => 'vendido',
            'separado' => 'reservado',
            'reservado' => 'reservado',
            'bloqueado' => 'bloqueado',
            'no vendible' => 'bloqueado',
            'en proceso' => 'reservado',
            'pre-venta' => 'disponible',
        ];

        return $statusMap[$status] ?? 'disponible';
    }
}
