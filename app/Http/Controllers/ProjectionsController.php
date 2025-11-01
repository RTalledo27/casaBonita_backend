<?php

namespace App\Http\Controllers;

use App\Services\ProjectionsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProjectionsController extends Controller
{
    protected $projectionsService;

    public function __construct(ProjectionsService $projectionsService)
    {
        $this->projectionsService = $projectionsService;
    }

    /**
     * Get financial projections
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'projection_type' => 'required|in:revenue,sales,collections',
            'period_months' => 'required|integer|min:1|max:24',
            'base_date' => 'nullable|date',
            'office_id' => 'nullable|integer|exists:offices,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $projectionType = $request->get('projection_type');
            $periodMonths = $request->get('period_months');
            $baseDate = $request->get('base_date', now()->format('Y-m-d'));
            $officeId = $request->get('office_id');

            $projections = $this->projectionsService->getProjections(
                $projectionType,
                $periodMonths,
                $baseDate,
                $officeId
            );

            return response()->json([
                'success' => true,
                'projections' => $projections['projections'],
                'trends' => $projections['trends'],
                'kpis' => $projections['kpis']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proyecciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue projections
     */
    public function revenue(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period_months' => 'required|integer|min:1|max:24',
            'base_date' => 'nullable|date',
            'office_id' => 'nullable|integer|exists:offices,id',
            'include_trends' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $periodMonths = $request->get('period_months');
            $baseDate = $request->get('base_date', now()->format('Y-m-d'));
            $officeId = $request->get('office_id');
            $includeTrends = $request->get('include_trends', true);

            $revenueProjections = $this->projectionsService->getRevenueProjections(
                $periodMonths,
                $baseDate,
                $officeId,
                $includeTrends
            );

            return response()->json([
                'success' => true,
                'data' => $revenueProjections
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proyecciones de ingresos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales projections
     */
    public function sales(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period_months' => 'required|integer|min:1|max:24',
            'base_date' => 'nullable|date',
            'office_id' => 'nullable|integer|exists:offices,id',
            'advisor_id' => 'nullable|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $periodMonths = $request->get('period_months');
            $baseDate = $request->get('base_date', now()->format('Y-m-d'));
            $officeId = $request->get('office_id');
            $advisorId = $request->get('advisor_id');

            $salesProjections = $this->projectionsService->getSalesProjections(
                $periodMonths,
                $baseDate,
                $officeId,
                $advisorId
            );

            return response()->json([
                'success' => true,
                'data' => $salesProjections
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proyecciones de ventas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get collections projections
     */
    public function collections(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period_months' => 'required|integer|min:1|max:24',
            'base_date' => 'nullable|date',
            'office_id' => 'nullable|integer|exists:offices,id',
            'include_overdue' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $periodMonths = $request->get('period_months');
            $baseDate = $request->get('base_date', now()->format('Y-m-d'));
            $officeId = $request->get('office_id');
            $includeOverdue = $request->get('include_overdue', true);

            $collectionsProjections = $this->projectionsService->getCollectionsProjections(
                $periodMonths,
                $baseDate,
                $officeId,
                $includeOverdue
            );

            return response()->json([
                'success' => true,
                'data' => $collectionsProjections
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proyecciones de cobranza',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get KPIs dashboard
     */
    public function kpis(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'nullable|in:monthly,quarterly,yearly',
            'office_id' => 'nullable|integer|exists:offices,id',
            'compare_previous' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $period = $request->get('period', 'monthly');
            $officeId = $request->get('office_id');
            $comparePrevious = $request->get('compare_previous', true);

            $kpis = $this->projectionsService->getKPIs($period, $officeId, $comparePrevious);

            return response()->json([
                'success' => true,
                'data' => $kpis
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener KPIs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get trend analysis
     */
    public function trends(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'metric' => 'required|in:sales,revenue,collections,conversion',
            'period' => 'required|in:daily,weekly,monthly,quarterly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'office_id' => 'nullable|integer|exists:offices,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $metric = $request->get('metric');
            $period = $request->get('period');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $officeId = $request->get('office_id');

            $trends = $this->projectionsService->getTrendAnalysis(
                $metric,
                $period,
                $startDate,
                $endDate,
                $officeId
            );

            return response()->json([
                'success' => true,
                'data' => $trends
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener análisis de tendencias',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}