<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LogicwareApiService;
use App\Services\LogicwareLotImportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * Controlador para importación de lotes desde LogicWare API
 * 
 * Maneja endpoints para:
 * - Obtener stages/etapas disponibles
 * - Previsualizar stock de una etapa
 * - Importar lotes de una etapa seleccionada
 */
class LogicwareLotImportController extends Controller
{
    protected LogicwareApiService $logicwareApi;
    protected LogicwareLotImportService $importService;

    public function __construct(
        LogicwareApiService $logicwareApi,
        LogicwareLotImportService $importService
    ) {
        $this->logicwareApi = $logicwareApi;
        $this->importService = $importService;
    }

    /**
     * Obtener etapas/stages disponibles del proyecto
     * 
     * GET /api/logicware/stages
     * 
     * Query params:
     * - projectCode: string (default: 'casabonita')
     * - forceRefresh: boolean (default: false)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStages(Request $request): JsonResponse
    {
        try {
            $projectCode = $request->input('projectCode', 'casabonita');
            $forceRefresh = $request->boolean('forceRefresh', false);

            Log::info('[LogicwareLotImportController] Obteniendo stages', [
                'projectCode' => $projectCode,
                'forceRefresh' => $forceRefresh
            ]);

            $stages = $this->logicwareApi->getStages($projectCode, $forceRefresh);

            return response()->json([
                'success' => true,
                'message' => 'Etapas obtenidas exitosamente',
                'data' => $stages['data'] ?? [],
                'meta' => [
                    'total' => isset($stages['data']) ? count($stages['data']) : 0,
                    'cached_at' => $stages['cached_at'] ?? null,
                    'is_mock' => $stages['is_mock'] ?? false,
                    'projectCode' => $projectCode
                ]
            ]);

        } catch (Exception $e) {
            Log::error('[LogicwareLotImportController] Error obteniendo stages', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener etapas: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Previsualizar stock de una etapa específica
     * 
     * GET /api/logicware/stages/{stageId}/preview
     * 
     * Query params:
     * - projectCode: string (default: 'casabonita')
     * - forceRefresh: boolean (default: false)
     * 
     * @param Request $request
     * @param string $stageId
     * @return JsonResponse
     */
    public function previewStageStock(Request $request, string $stageId): JsonResponse
    {
        try {
            $projectCode = $request->input('projectCode', 'casabonita');
            $forceRefresh = $request->boolean('forceRefresh', false);

            Log::info('[LogicwareLotImportController] Previsualizando stock de stage', [
                'stageId' => $stageId,
                'projectCode' => $projectCode
            ]);

            $stock = $this->logicwareApi->getStockByStage($projectCode, $stageId, $forceRefresh);

            // Enriquecer datos con información de importabilidad
            $enrichedData = $this->enrichStockData($stock['data'] ?? []);

            return response()->json([
                'success' => true,
                'message' => 'Vista previa del stock obtenida exitosamente',
                'data' => $enrichedData,
                'meta' => [
                    'total' => count($enrichedData),
                    'importable' => collect($enrichedData)->where('can_import', true)->count(),
                    'duplicates' => collect($enrichedData)->where('exists', true)->count(),
                    'cached_at' => $stock['cached_at'] ?? null,
                    'is_mock' => $stock['is_mock'] ?? false,
                    'stageId' => $stageId,
                    'projectCode' => $projectCode
                ]
            ]);

        } catch (Exception $e) {
            Log::error('[LogicwareLotImportController] Error en preview', [
                'stageId' => $stageId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener vista previa: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Importar lotes de una etapa específica
     * 
     * POST /api/logicware/stages/{stageId}/import
     * 
     * Body params:
     * - projectCode: string (required)
     * - options: object (optional)
     *   - update_existing: boolean (default: false)
     *   - create_manzanas: boolean (default: true)
     *   - create_templates: boolean (default: true)
     *   - update_templates: boolean (default: true)
     *   - update_status: boolean (default: false)
     *   - force_refresh: boolean (default: false)
     * 
     * @param Request $request
     * @param string $stageId
     * @return JsonResponse
     */
    public function importStage(Request $request, string $stageId): JsonResponse
    {
        try {
            // Validar request
            $validator = Validator::make($request->all(), [
                'projectCode' => 'required|string',
                'options' => 'sometimes|array',
                'options.update_existing' => 'sometimes|boolean',
                'options.create_manzanas' => 'sometimes|boolean',
                'options.create_templates' => 'sometimes|boolean',
                'options.update_templates' => 'sometimes|boolean',
                'options.update_status' => 'sometimes|boolean',
                'options.force_refresh' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $projectCode = $request->input('projectCode');
            $options = $request->input('options', []);

            // Valores por defecto
            $options = array_merge([
                'update_existing' => false,
                'create_manzanas' => true,
                'create_templates' => true,
                'update_templates' => true,
                'update_status' => false,
                'force_refresh' => false
            ], $options);

            Log::info('[LogicwareLotImportController] ⚡ Iniciando importación de stage', [
                'stageId' => $stageId,
                'projectCode' => $projectCode,
                'options' => $options
            ]);

            // Realizar importación
            $result = $this->importService->importLotsByStage($projectCode, $stageId, $options);

            $statusCode = $result['success'] ? 200 : 500;

            return response()->json($result, $statusCode);

        } catch (Exception $e) {
            Log::error('[LogicwareLotImportController] ❌ Error en importación', [
                'stageId' => $stageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error crítico en la importación: ' . $e->getMessage(),
                'stats' => $this->importService->getStats(),
                'errors' => $this->importService->getErrors()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de conexión con LogicWare
     * 
     * GET /api/logicware/connection-stats
     * 
     * @return JsonResponse
     */
    public function getConnectionStats(): JsonResponse
    {
        try {
            $dailyRequests = $this->logicwareApi->getDailyRequestCount();
            $hasAvailable = $this->logicwareApi->hasAvailableRequests();

            return response()->json([
                'success' => true,
                'data' => [
                    'daily_requests_used' => $dailyRequests,
                    'daily_requests_limit' => 4,
                    'daily_requests_remaining' => max(0, 4 - $dailyRequests),
                    'has_available_requests' => $hasAvailable,
                    'connection_status' => 'connected'
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'data' => [
                    'connection_status' => 'error',
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Limpiar caché de LogicWare
     * 
     * POST /api/logicware/clear-cache
     * 
     * @return JsonResponse
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->logicwareApi->clearCache();

            Log::info('[LogicwareLotImportController] Caché limpiado exitosamente');

            return response()->json([
                'success' => true,
                'message' => 'Caché de LogicWare limpiado exitosamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar caché: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enriquecer datos de stock con información de importabilidad
     * 
     * @param array $stockData
     * @return array
     */
    protected function enrichStockData(array $stockData): array
    {
        return array_map(function ($unit) {
            // Parsear código para verificar si se puede importar
            $code = $unit['code'] ?? null;
            $canImport = !empty($code);

            // Verificar si ya existe en la base de datos
            $exists = false;
            $existingLot = null;

            if ($code && preg_match('/^([A-Z0-9]+)-(\d+)$/', $code, $matches)) {
                $manzanaName = $matches[1];
                $lotNumber = $matches[2];

                $manzana = \Modules\Inventory\Models\Manzana::where('name', $manzanaName)->first();
                
                if ($manzana) {
                    $existingLot = \Modules\Inventory\Models\Lot::where('num_lot', $lotNumber)
                        ->where('manzana_id', $manzana->manzana_id)
                        ->first();
                    
                    $exists = $existingLot !== null;
                }
            }

            return array_merge($unit, [
                'can_import' => $canImport,
                'exists' => $exists,
                'existing_lot_id' => $existingLot ? $existingLot->lot_id : null,
                'import_action' => $exists ? 'update' : 'create'
            ]);
        }, $stockData);
    }
}
