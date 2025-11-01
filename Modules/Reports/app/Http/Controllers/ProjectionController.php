<?php

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Reports\Services\ProjectionService;

class ProjectionController extends Controller
{
    protected $projectionService;

    public function __construct(ProjectionService $projectionService)
    {
        $this->projectionService = $projectionService;
    }

    /**
     * Get revenue projections based on historical data and linear regression
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getRevenueProjection(Request $request): JsonResponse
    {
        $request->validate([
            'months_ahead' => 'nullable|integer|min:1|max:24',
            'months_back' => 'nullable|integer|min:1|max:36'
        ]);

        try {
            $monthsAhead = $request->input('months_ahead', 6);
            $monthsBack = $request->input('months_back', 12);

            \Log::info('📊 Getting monthly revenue projection', [
                'months_ahead' => $monthsAhead,
                'months_back' => $monthsBack
            ]);

            $projection = $this->projectionService->getRevenueProjection($monthsAhead, $monthsBack);

            return response()->json([
                'success' => true,
                'data' => $projection,
                'message' => 'Proyección generada exitosamente'
            ]);
        } catch (\Exception $e) {
            \Log::error('❌ Error getting revenue projection: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar proyección',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comparison between actual and projected for specific quarter
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getQuarterComparison(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'quarter' => 'required|integer|min:1|max:4'
        ]);

        try {
            $year = $request->input('year');
            $quarter = $request->input('quarter');

            \Log::info('📊 Getting quarter comparison', [
                'year' => $year,
                'quarter' => $quarter
            ]);

            $comparison = $this->projectionService->getQuarterComparison($year, $quarter);

            return response()->json([
                'success' => true,
                'data' => $comparison
            ]);
        } catch (\Exception $e) {
            \Log::error('❌ Error getting quarter comparison: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al comparar trimestre',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get quick projection summary (for dashboard cards)
     * 
     * @return JsonResponse
     */
    public function getProjectionSummary(): JsonResponse
    {
        try {
            \Log::info('📊 Getting projection summary for dashboard');

            $projection = $this->projectionService->getRevenueProjection(4, 2);

            // Extract key metrics for dashboard
            $summary = $projection['summary'] ?? [];
            $currentQuarter = $projection['current_quarter'] ?? [];

            return response()->json([
                'success' => true,
                'data' => [
                    'current_quarter' => [
                        'label' => $currentQuarter['quarter_label'] ?? '',
                        'actual_revenue' => $currentQuarter['actual_revenue'] ?? 0,
                        'projected_end' => $currentQuarter['projected_quarter_end'] ?? 0,
                        'progress' => $currentQuarter['progress_percentage'] ?? 0
                    ],
                    'growth_rate' => $summary['average_growth_rate'] ?? 0,
                    'trend' => $summary['trend'] ?? 'Estable',
                    'next_quarter_projection' => $summary['next_quarter_projection'] ?? 0,
                    'quarters_analyzed' => $summary['quarters_analyzed'] ?? 0
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('❌ Error getting projection summary: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen de proyección',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
