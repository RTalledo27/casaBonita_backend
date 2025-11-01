<?php

namespace Modules\Reports\Services;

use Modules\Reports\Repositories\ProjectionRepository;
use Carbon\Carbon;

class ProjectionService
{
    protected $projectionRepository;

    public function __construct(ProjectionRepository $projectionRepository)
    {
        $this->projectionRepository = $projectionRepository;
    }

    /**
     * Get comprehensive revenue projection analysis (monthly)
     */
    public function getRevenueProjection($monthsAhead = 6, $monthsBack = 12)
    {
        // Get historical monthly data
        $historicalData = $this->projectionRepository->getMonthlyRevenue($monthsBack);
        
        if (empty($historicalData)) {
            return [
                'error' => 'No hay datos históricos suficientes para generar proyecciones',
                'historical_data' => [],
                'projections' => [],
                'growth_analysis' => [],
                'current_month' => null
            ];
        }

        // Get current month performance
        $currentMonth = $this->projectionRepository->getCurrentMonthRevenue();

        // Calculate projections using linear regression
        $projections = $this->projectionRepository->projectFutureMonths($historicalData, $monthsAhead);

        // Calculate growth rates
        $growthAnalysis = $this->projectionRepository->calculateGrowthRates($historicalData);

        // Detect seasonal patterns
        $seasonalFactors = $this->projectionRepository->detectSeasonality($historicalData);

        // Apply seasonal adjustment to projections (optional enhancement)
        $adjustedProjections = $this->applySeasonalAdjustment($projections, $seasonalFactors);

        // Calculate regression quality
        $regression = $this->projectionRepository->calculateLinearRegression($historicalData);

        return [
            'historical_data' => $historicalData,
            'current_month' => $currentMonth,
            'projections' => $adjustedProjections,
            'growth_analysis' => $growthAnalysis,
            'seasonal_factors' => $seasonalFactors,
            'regression_quality' => [
                'r_squared' => round($regression['r_squared'], 4),
                'slope' => round($regression['slope'], 2),
                'interpretation' => $this->interpretRSquared($regression['r_squared'])
            ],
            'summary' => $this->generateSummary($historicalData, $currentMonth, $adjustedProjections, $growthAnalysis)
        ];
    }

    /**
     * Apply seasonal adjustment to projections (works with monthly or quarterly)
     */
    protected function applySeasonalAdjustment($projections, $seasonalFactors)
    {
        return array_map(function($projection) use ($seasonalFactors) {
            // Try monthly first, then quarterly
            if (isset($projection['month'])) {
                $monthNames = [
                    1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 
                    5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 
                    9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
                ];
                $periodKey = $monthNames[$projection['month']];
            } else {
                $periodKey = "Q{$projection['quarter']}";
            }
            
            $seasonalFactor = $seasonalFactors[$periodKey] ?? 1;
            
            return array_merge($projection, [
                'projected_revenue_base' => $projection['projected_revenue'],
                'projected_revenue' => round($projection['projected_revenue'] * $seasonalFactor, 2),
                'seasonal_factor' => round($seasonalFactor, 2)
            ]);
        }, $projections);
    }

    /**
     * Interpret R² value for users
     */
    protected function interpretRSquared($rSquared)
    {
        if ($rSquared >= 0.9) {
            return 'Excelente - La proyección es muy confiable';
        } elseif ($rSquared >= 0.7) {
            return 'Buena - La proyección es confiable';
        } elseif ($rSquared >= 0.5) {
            return 'Moderada - La proyección tiene cierta incertidumbre';
        } else {
            return 'Baja - La proyección tiene alta incertidumbre';
        }
    }

    /**
     * Generate executive summary (works with monthly or quarterly data)
     */
    protected function generateSummary($historicalData, $currentPeriod, $projections, $growthAnalysis)
    {
        $lastPeriod = end($historicalData);
        $nextPeriod = $projections[0] ?? null;

        $totalHistorical = array_sum(array_column($historicalData, 'total_revenue'));
        $totalProjected = array_sum(array_column($projections, 'projected_revenue'));

        // Support both monthly and quarterly projected end keys
        $projectedEnd = $currentPeriod['projected_month_end'] ?? $currentPeriod['projected_quarter_end'] ?? 0;

        return [
            'last_quarter_revenue' => $lastPeriod['total_revenue'] ?? 0,
            'current_quarter_actual' => $currentPeriod['actual_revenue'] ?? 0,
            'current_quarter_projected_end' => $projectedEnd,
            'next_quarter_projection' => $nextPeriod['projected_revenue'] ?? 0,
            'total_historical_revenue' => round($totalHistorical, 2),
            'total_projected_revenue' => round($totalProjected, 2),
            'average_growth_rate' => $growthAnalysis['average_growth_rate'] ?? 0,
            'quarters_analyzed' => count($historicalData),
            'quarters_projected' => count($projections),
            'trend' => $this->determineTrend($growthAnalysis['average_growth_rate'] ?? 0)
        ];
    }

    /**
     * Determine trend direction
     */
    protected function determineTrend($avgGrowthRate)
    {
        if ($avgGrowthRate > 5) {
            return 'Crecimiento fuerte';
        } elseif ($avgGrowthRate > 0) {
            return 'Crecimiento moderado';
        } elseif ($avgGrowthRate > -5) {
            return 'Estable';
        } else {
            return 'Decrecimiento';
        }
    }

    /**
     * Get comparison: actual vs projected for specific quarter
     */
    public function getQuarterComparison($year, $quarter)
    {
        $historicalData = $this->projectionRepository->getQuarterlyRevenue(2);
        
        // Find actual data for this quarter
        $actual = collect($historicalData)->first(function($item) use ($year, $quarter) {
            return $item['year'] == $year && $item['quarter'] == $quarter;
        });

        if (!$actual) {
            return [
                'error' => 'No hay datos para este trimestre',
                'quarter' => "Q{$quarter} {$year}"
            ];
        }

        // Calculate what was projected for this quarter using previous data
        $previousData = collect($historicalData)->filter(function($item) use ($year, $quarter) {
            return ($item['year'] < $year) || 
                   ($item['year'] == $year && $item['quarter'] < $quarter);
        })->values()->toArray();

        if (empty($previousData)) {
            return [
                'quarter' => "Q{$quarter} {$year}",
                'actual_revenue' => $actual['total_revenue'],
                'projected_revenue' => null,
                'variance' => null,
                'message' => 'No hay datos históricos previos para calcular proyección'
            ];
        }

        $projections = $this->projectionRepository->projectFutureQuarters($previousData, 1);
        $projected = $projections[0] ?? null;

        $variance = $projected ? $actual['total_revenue'] - $projected['projected_revenue'] : 0;
        $variancePercent = $projected && $projected['projected_revenue'] != 0
            ? ($variance / $projected['projected_revenue']) * 100
            : 0;

        return [
            'quarter' => "Q{$quarter} {$year}",
            'actual_revenue' => $actual['total_revenue'],
            'projected_revenue' => $projected['projected_revenue'] ?? null,
            'variance' => round($variance, 2),
            'variance_percent' => round($variancePercent, 2),
            'performance' => $this->evaluatePerformance($variancePercent)
        ];
    }

    /**
     * Evaluate performance based on variance
     */
    protected function evaluatePerformance($variancePercent)
    {
        if ($variancePercent > 10) {
            return 'Excelente - Superó expectativas';
        } elseif ($variancePercent > 0) {
            return 'Bueno - Cumplió expectativas';
        } elseif ($variancePercent > -10) {
            return 'Aceptable - Ligeramente bajo expectativas';
        } else {
            return 'Bajo - Requiere atención';
        }
    }
}
