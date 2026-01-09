<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SalesCutService;
use App\Models\SalesCut;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SalesCutController extends Controller
{
    protected SalesCutService $salesCutService;

    public function __construct(SalesCutService $salesCutService)
    {
        $this->salesCutService = $salesCutService;
    }

    /**
     * Obtener todos los cortes con paginación
     * GET /api/v1/sales/cuts
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $status = $request->input('status');
            $type = $request->input('type');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $query = SalesCut::with(['closedBy', 'reviewedBy']);

            if ($status) {
                $query->where('status', $status);
            }

            if ($type) {
                $query->where('cut_type', $type);
            }

            if ($startDate) {
                $query->whereDate('cut_date', '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate('cut_date', '<=', $endDate);
            }

            $cuts = $query->orderBy('cut_date', 'desc')
                         ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $cuts,
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al obtener cortes', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cortes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener corte del día actual
     * GET /api/v1/sales/cuts/today
     */
    public function today(): JsonResponse
    {
        try {
            $cut = $this->salesCutService->getTodayCut();

            if (!$cut) {
                // Crear corte si no existe
                $cut = $this->salesCutService->createDailyCut();
            }

            return response()->json([
                'success' => true,
                'data' => $cut->load(['items.contract.client', 'items.employee.user', 'closedBy']),
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al obtener corte del día', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener corte del día',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener un corte específico
     * GET /api/v1/sales/cuts/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $cut = SalesCut::with([
                'items.contract.client',
                'items.contract.lot',
                'items.employee.user',
                'items.paymentSchedule',
                'closedBy',
                'reviewedBy',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $cut,
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al obtener corte', [
                'cut_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener corte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear corte diario manualmente
     * POST /api/v1/sales/cuts/create-daily
     */
    public function createDaily(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'date' => 'nullable|date',
            ]);

            $cut = $this->salesCutService->createDailyCut($validated['date'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Corte diario creado exitosamente',
                'data' => $cut->load('items'),
            ], 201);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al crear corte diario', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear corte diario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cerrar un corte
     * POST /api/v1/sales/cuts/{id}/close
     */
    public function close(int $id, Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $cut = $this->salesCutService->closeCut($id, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Corte cerrado exitosamente',
                'data' => $cut,
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al cerrar corte', [
                'cut_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cerrar corte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marcar corte como revisado
     * POST /api/v1/sales/cuts/{id}/review
     */
    public function review(int $id, Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $cut = SalesCut::findOrFail($id);

            if ($cut->status !== 'closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'El corte debe estar cerrado antes de ser revisado',
                ], 400);
            }

            $cut->markAsReviewed($userId);

            return response()->json([
                'success' => true,
                'message' => 'Corte revisado exitosamente',
                'data' => $cut->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al revisar corte', [
                'cut_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al revisar corte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas del mes
     * GET /api/v1/sales/cuts/monthly-stats
     */
    public function monthlyStats(): JsonResponse
    {
        try {
            $stats = $this->salesCutService->getMonthlyStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al obtener estadísticas mensuales', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas mensuales',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar notas del corte
     * PATCH /api/v1/sales/cuts/{id}/notes
     */
    public function updateNotes(int $id, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'notes' => 'required|string|max:1000',
            ]);

            $cut = SalesCut::findOrFail($id);
            $cut->update(['notes' => $validated['notes']]);

            return response()->json([
                'success' => true,
                'message' => 'Notas actualizadas exitosamente',
                'data' => $cut,
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al actualizar notas', [
                'cut_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar notas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
