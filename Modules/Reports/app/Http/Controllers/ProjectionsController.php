<?php

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Reports\Services\ProjectionsService;

class ProjectionsController extends Controller
{
    protected $projectionsService;

    public function __construct(ProjectionsService $projectionsService)
    {
        $this->projectionsService = $projectionsService;
    }

    /**
     * Get sales projections
     */
    public function getSalesProjections(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|string|in:monthly,quarterly,yearly',
            'months_ahead' => 'nullable|integer|min:1|max:24',
            'project_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer'
        ]);

        try {
            $data = $this->projectionsService->getSalesProjections(
                $request->input('period'),
                $request->input('months_ahead', 12),
                $request->input('project_id'),
                $request->input('employee_id')
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proyecciones de ventas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cash flow projections
     */
    public function getCashFlowProjections(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|string|in:monthly,quarterly,yearly',
            'months_ahead' => 'nullable|integer|min:1|max:24',
            'include_pending' => 'nullable|boolean'
        ]);

        try {
            $data = $this->projectionsService->getCashFlowProjections(
                $request->input('period'),
                $request->input('months_ahead', 12),
                $request->input('include_pending', true)
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proyecciones de flujo de caja: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory projections
     */
    public function getInventoryProjections(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => 'nullable|integer',
            'months_ahead' => 'nullable|integer|min:1|max:24',
            'include_reserved' => 'nullable|boolean'
        ]);

        try {
            $data = $this->projectionsService->getInventoryProjections(
                $request->input('project_id'),
                $request->input('months_ahead', 12),
                $request->input('include_reserved', true)
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proyecciones de inventario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get market analysis projections
     */
    public function getMarketAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'region' => 'nullable|string',
            'property_type' => 'nullable|string',
            'months_ahead' => 'nullable|integer|min:1|max:24'
        ]);

        try {
            $data = $this->projectionsService->getMarketAnalysis(
                $request->input('region'),
                $request->input('property_type'),
                $request->input('months_ahead', 12)
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener análisis de mercado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ROI projections
     */
    public function getROIProjections(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => 'nullable|integer',
            'investment_amount' => 'nullable|numeric|min:0',
            'months_ahead' => 'nullable|integer|min:1|max:60'
        ]);

        try {
            $data = $this->projectionsService->getROIProjections(
                $request->input('project_id'),
                $request->input('investment_amount'),
                $request->input('months_ahead', 24)
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proyecciones de ROI: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get scenario analysis
     */
    public function getScenarioAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'scenario_type' => 'required|string|in:optimistic,realistic,pessimistic',
            'project_id' => 'nullable|integer',
            'months_ahead' => 'nullable|integer|min:1|max:24',
            'variables' => 'nullable|array'
        ]);

        try {
            $data = $this->projectionsService->getScenarioAnalysis(
                $request->input('scenario_type'),
                $request->input('project_id'),
                $request->input('months_ahead', 12),
                $request->input('variables', [])
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener análisis de escenarios: ' . $e->getMessage()
            ], 500);
        }
    }
}
