<?php

namespace App\Services;

use App\Repositories\ProjectionsRepository;
use App\Repositories\SalesRepository;
use App\Repositories\PaymentsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ProjectionsService
{
    protected $projectionsRepository;
    protected $salesRepository;
    protected $paymentsRepository;

    public function __construct(
        ProjectionsRepository $projectionsRepository,
        SalesRepository $salesRepository,
        PaymentsRepository $paymentsRepository
    ) {
        $this->projectionsRepository = $projectionsRepository;
        $this->salesRepository = $salesRepository;
        $this->paymentsRepository = $paymentsRepository;
    }

    /**
     * Get projections based on type and parameters
     */
    public function getProjections(string $projectionType, int $periodMonths, string $baseDate, ?int $officeId = null): array
    {
        $cacheKey = 'projections_' . md5($projectionType . $periodMonths . $baseDate . ($officeId ?? 'all'));
        
        return Cache::remember($cacheKey, 600, function () use ($projectionType, $periodMonths, $baseDate, $officeId) {
            switch ($projectionType) {
                case 'revenue':
                    return $this->getRevenueProjections($periodMonths, $baseDate, $officeId);
                case 'sales':
                    return $this->getSalesProjections($periodMonths, $baseDate, $officeId);
                case 'collections':
                    return $this->getCollectionsProjections($periodMonths, $baseDate, $officeId);
                default:
                    throw new \Exception('Tipo de proyección no válido');
            }
        });
    }

    /**
     * Get revenue projections
     */
    public function getRevenueProjections(int $periodMonths, string $baseDate, ?int $officeId = null, bool $includeTrends = true): array
    {
        $baseDateTime = Carbon::parse($baseDate);
        $projections = [];
        
        // Get historical data for trend analysis
        $historicalData = $this->getHistoricalRevenueData($baseDateTime, $officeId);
        $trendMultiplier = $this->calculateTrendMultiplier($historicalData);
        
        for ($i = 1; $i <= $periodMonths; $i++) {
            $projectionDate = $baseDateTime->copy()->addMonths($i);
            
            // Base projection using historical averages
            $baseProjection = $this->calculateBaseRevenueProjection($projectionDate, $officeId);
            
            // Apply trend multiplier
            $adjustedProjection = $baseProjection * $trendMultiplier;
            
            // Apply seasonal adjustments
            $seasonalAdjustment = $this->getSeasonalAdjustment($projectionDate->month);
            $finalProjection = $adjustedProjection * $seasonalAdjustment;
            
            $projections[] = [
                'period' => $projectionDate->format('Y-m'),
                'month_name' => $projectionDate->format('F Y'),
                'projected_revenue' => round($finalProjection, 2),
                'base_projection' => round($baseProjection, 2),
                'trend_adjustment' => round(($trendMultiplier - 1) * 100, 2),
                'seasonal_adjustment' => round(($seasonalAdjustment - 1) * 100, 2)
            ];
        }
        
        $result = [
            'projections' => $projections,
            'kpis' => $this->calculateRevenueKPIs($projections, $historicalData)
        ];
        
        if ($includeTrends) {
            $result['trends'] = $this->calculateRevenueTrends($historicalData, $projections);
        }
        
        return $result;
    }

    /**
     * Get sales projections
     */
    public function getSalesProjections(int $periodMonths, string $baseDate, ?int $officeId = null, ?int $advisorId = null): array
    {
        $baseDateTime = Carbon::parse($baseDate);
        $projections = [];
        
        // Get historical sales data
        $historicalData = $this->getHistoricalSalesData($baseDateTime, $officeId, $advisorId);
        $averageMonthlySales = $this->calculateAverageMonthlySales($historicalData);
        $trendMultiplier = $this->calculateSalesTrendMultiplier($historicalData);
        
        for ($i = 1; $i <= $periodMonths; $i++) {
            $projectionDate = $baseDateTime->copy()->addMonths($i);
            
            // Base projection
            $baseProjection = $averageMonthlySales;
            
            // Apply trend
            $trendAdjustedProjection = $baseProjection * pow($trendMultiplier, $i);
            
            // Apply seasonal adjustments
            $seasonalAdjustment = $this->getSalesSeasonalAdjustment($projectionDate->month);
            $finalProjection = $trendAdjustedProjection * $seasonalAdjustment;
            
            $projections[] = [
                'period' => $projectionDate->format('Y-m'),
                'month_name' => $projectionDate->format('F Y'),
                'projected_sales_count' => round($finalProjection),
                'projected_sales_value' => round($finalProjection * $this->getAverageSaleValue($historicalData), 2),
                'confidence_level' => $this->calculateConfidenceLevel($i, $historicalData)
            ];
        }
        
        return [
            'projections' => $projections,
            'kpis' => $this->calculateSalesKPIs($projections, $historicalData),
            'trends' => $this->calculateSalesTrends($historicalData, $projections)
        ];
    }

    /**
     * Get collections projections
     */
    public function getCollectionsProjections(int $periodMonths, string $baseDate, ?int $officeId = null, bool $includeOverdue = true): array
    {
        $baseDateTime = Carbon::parse($baseDate);
        $projections = [];
        
        // Get scheduled payments for the projection period
        $scheduledPayments = $this->getScheduledPayments($baseDateTime, $periodMonths, $officeId);
        
        // Get historical collection rates
        $historicalCollectionRate = $this->getHistoricalCollectionRate($baseDateTime, $officeId);
        
        for ($i = 1; $i <= $periodMonths; $i++) {
            $projectionDate = $baseDateTime->copy()->addMonths($i);
            $monthKey = $projectionDate->format('Y-m');
            
            $scheduledAmount = $scheduledPayments[$monthKey] ?? 0;
            $projectedCollection = $scheduledAmount * $historicalCollectionRate;
            
            // Add overdue collections if requested
            $overdueCollection = 0;
            if ($includeOverdue) {
                $overdueCollection = $this->calculateOverdueCollectionProjection($projectionDate, $officeId);
            }
            
            $projections[] = [
                'period' => $monthKey,
                'month_name' => $projectionDate->format('F Y'),
                'scheduled_amount' => round($scheduledAmount, 2),
                'projected_collection' => round($projectedCollection, 2),
                'overdue_collection' => round($overdueCollection, 2),
                'total_projected' => round($projectedCollection + $overdueCollection, 2),
                'collection_rate' => round($historicalCollectionRate * 100, 2)
            ];
        }
        
        return [
            'projections' => $projections,
            'kpis' => $this->calculateCollectionKPIs($projections),
            'trends' => $this->calculateCollectionTrends($projections)
        ];
    }

    /**
     * Get KPIs dashboard
     */
    public function getKPIs(string $period = 'monthly', ?int $officeId = null, bool $comparePrevious = true): array
    {
        $cacheKey = 'kpis_' . $period . '_' . ($officeId ?? 'all') . '_' . ($comparePrevious ? 'compare' : 'no_compare');
        
        return Cache::remember($cacheKey, 300, function () use ($period, $officeId, $comparePrevious) {
            $currentPeriodData = $this->getCurrentPeriodData($period, $officeId);
            $kpis = [
                'revenue' => [
                    'current' => $currentPeriodData['revenue'],
                    'target' => $this->getRevenueTarget($period, $officeId),
                    'achievement' => $this->calculateAchievementRate($currentPeriodData['revenue'], $this->getRevenueTarget($period, $officeId))
                ],
                'sales' => [
                    'current' => $currentPeriodData['sales_count'],
                    'target' => $this->getSalesTarget($period, $officeId),
                    'achievement' => $this->calculateAchievementRate($currentPeriodData['sales_count'], $this->getSalesTarget($period, $officeId))
                ],
                'collections' => [
                    'current' => $currentPeriodData['collections'],
                    'target' => $this->getCollectionTarget($period, $officeId),
                    'achievement' => $this->calculateAchievementRate($currentPeriodData['collections'], $this->getCollectionTarget($period, $officeId))
                ],
                'conversion_rate' => [
                    'current' => $currentPeriodData['conversion_rate'],
                    'target' => 15.0, // Default target
                    'achievement' => $this->calculateAchievementRate($currentPeriodData['conversion_rate'], 15.0)
                ]
            ];
            
            if ($comparePrevious) {
                $previousPeriodData = $this->getPreviousPeriodData($period, $officeId);
                foreach ($kpis as $key => &$kpi) {
                    $kpi['previous'] = $previousPeriodData[$key] ?? 0;
                    $kpi['growth'] = $this->calculateGrowthRate($kpi['previous'], $kpi['current']);
                }
            }
            
            return $kpis;
        });
    }

    /**
     * Get trend analysis
     */
    public function getTrendAnalysis(string $metric, string $period, string $startDate, string $endDate, ?int $officeId = null): array
    {
        $cacheKey = 'trend_analysis_' . md5($metric . $period . $startDate . $endDate . ($officeId ?? 'all'));
        
        return Cache::remember($cacheKey, 600, function () use ($metric, $period, $startDate, $endDate, $officeId) {
            return $this->projectionsRepository->getTrendAnalysis($metric, $period, $startDate, $endDate, $officeId);
        });
    }

    // Helper methods

    protected function getHistoricalRevenueData(Carbon $baseDate, ?int $officeId = null): array
    {
        $startDate = $baseDate->copy()->subMonths(12);
        return $this->salesRepository->getMonthlyRevenue($startDate->format('Y-m-d'), $baseDate->format('Y-m-d'), $officeId);
    }

    protected function getHistoricalSalesData(Carbon $baseDate, ?int $officeId = null, ?int $advisorId = null): array
    {
        $startDate = $baseDate->copy()->subMonths(12);
        return $this->salesRepository->getMonthlySalesData($startDate->format('Y-m-d'), $baseDate->format('Y-m-d'), $officeId, $advisorId);
    }

    protected function calculateTrendMultiplier(array $historicalData): float
    {
        if (count($historicalData) < 3) {
            return 1.0; // No trend if insufficient data
        }
        
        $values = array_column($historicalData, 'revenue');
        $n = count($values);
        $sumX = array_sum(range(1, $n));
        $sumY = array_sum($values);
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $y = $values[$i];
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $avgY = $sumY / $n;
        
        return $avgY > 0 ? 1 + ($slope / $avgY) : 1.0;
    }

    protected function getSeasonalAdjustment(int $month): float
    {
        // Seasonal adjustments based on typical real estate patterns
        $seasonalFactors = [
            1 => 0.85,  // January - slower
            2 => 0.90,  // February
            3 => 1.10,  // March - spring pickup
            4 => 1.15,  // April
            5 => 1.20,  // May - peak season
            6 => 1.15,  // June
            7 => 1.05,  // July
            8 => 1.00,  // August
            9 => 1.10,  // September - fall pickup
            10 => 1.05, // October
            11 => 0.95, // November
            12 => 0.80  // December - holidays
        ];
        
        return $seasonalFactors[$month] ?? 1.0;
    }

    protected function calculateBaseRevenueProjection(Carbon $projectionDate, ?int $officeId = null): float
    {
        // Get average revenue from last 6 months
        $startDate = $projectionDate->copy()->subMonths(6);
        $endDate = $projectionDate->copy()->subMonth();
        
        $historicalRevenue = $this->salesRepository->getAverageMonthlyRevenue(
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $officeId
        );
        
        return $historicalRevenue;
    }

    protected function calculateAverageMonthlySales(array $historicalData): float
    {
        if (empty($historicalData)) {
            return 0;
        }
        
        $totalSales = array_sum(array_column($historicalData, 'sales_count'));
        return $totalSales / count($historicalData);
    }

    protected function calculateSalesTrendMultiplier(array $historicalData): float
    {
        if (count($historicalData) < 3) {
            return 1.0;
        }
        
        $values = array_column($historicalData, 'sales_count');
        $recentAvg = array_sum(array_slice($values, -3)) / 3;
        $olderAvg = array_sum(array_slice($values, 0, 3)) / 3;
        
        return $olderAvg > 0 ? $recentAvg / $olderAvg : 1.0;
    }

    protected function getSalesSeasonalAdjustment(int $month): float
    {
        return $this->getSeasonalAdjustment($month);
    }

    protected function getAverageSaleValue(array $historicalData): float
    {
        if (empty($historicalData)) {
            return 0;
        }
        
        $totalRevenue = array_sum(array_column($historicalData, 'revenue'));
        $totalSales = array_sum(array_column($historicalData, 'sales_count'));
        
        return $totalSales > 0 ? $totalRevenue / $totalSales : 0;
    }

    protected function calculateConfidenceLevel(int $monthsAhead, array $historicalData): float
    {
        $baseConfidence = 95;
        $decayRate = 5; // 5% decrease per month
        
        return max(50, $baseConfidence - ($monthsAhead * $decayRate));
    }

    protected function getScheduledPayments(Carbon $baseDate, int $periodMonths, ?int $officeId = null): array
    {
        return $this->paymentsRepository->getScheduledPaymentsByMonth(
            $baseDate->format('Y-m-d'),
            $baseDate->copy()->addMonths($periodMonths)->format('Y-m-d'),
            $officeId
        );
    }

    protected function getHistoricalCollectionRate(Carbon $baseDate, ?int $officeId = null): float
    {
        $startDate = $baseDate->copy()->subMonths(6);
        return $this->paymentsRepository->getAverageCollectionRate(
            $startDate->format('Y-m-d'),
            $baseDate->format('Y-m-d'),
            $officeId
        );
    }

    protected function calculateOverdueCollectionProjection(Carbon $projectionDate, ?int $officeId = null): float
    {
        // Estimate overdue collections based on historical recovery rates
        $overdueAmount = $this->paymentsRepository->getTotalOverdueAmount($officeId);
        $recoveryRate = 0.15; // Assume 15% monthly recovery rate for overdue amounts
        
        return $overdueAmount * $recoveryRate;
    }

    protected function calculateRevenueKPIs(array $projections, array $historicalData): array
    {
        $totalProjected = array_sum(array_column($projections, 'projected_revenue'));
        $averageMonthly = $totalProjected / count($projections);
        
        return [
            'total_projected_revenue' => round($totalProjected, 2),
            'average_monthly_revenue' => round($averageMonthly, 2),
            'growth_rate' => $this->calculateProjectedGrowthRate($historicalData, $projections),
            'confidence_score' => $this->calculateOverallConfidence($projections)
        ];
    }

    protected function calculateSalesKPIs(array $projections, array $historicalData): array
    {
        $totalProjectedSales = array_sum(array_column($projections, 'projected_sales_count'));
        $totalProjectedValue = array_sum(array_column($projections, 'projected_sales_value'));
        
        return [
            'total_projected_sales' => round($totalProjectedSales),
            'total_projected_value' => round($totalProjectedValue, 2),
            'average_sale_value' => round($totalProjectedValue / max($totalProjectedSales, 1), 2),
            'growth_rate' => $this->calculateProjectedGrowthRate($historicalData, $projections)
        ];
    }

    protected function calculateCollectionKPIs(array $projections): array
    {
        $totalScheduled = array_sum(array_column($projections, 'scheduled_amount'));
        $totalProjected = array_sum(array_column($projections, 'total_projected'));
        
        return [
            'total_scheduled' => round($totalScheduled, 2),
            'total_projected_collection' => round($totalProjected, 2),
            'projected_collection_rate' => round(($totalProjected / max($totalScheduled, 1)) * 100, 2),
            'efficiency_score' => round(($totalProjected / max($totalScheduled, 1)) * 100, 2)
        ];
    }

    protected function calculateRevenueTrends(array $historicalData, array $projections): array
    {
        return [
            'historical_trend' => $this->calculateTrendDirection($historicalData, 'revenue'),
            'projected_trend' => $this->calculateTrendDirection($projections, 'projected_revenue'),
            'volatility' => $this->calculateVolatility($historicalData, 'revenue')
        ];
    }

    protected function calculateSalesTrends(array $historicalData, array $projections): array
    {
        return [
            'historical_trend' => $this->calculateTrendDirection($historicalData, 'sales_count'),
            'projected_trend' => $this->calculateTrendDirection($projections, 'projected_sales_count'),
            'seasonality_impact' => $this->calculateSeasonalityImpact($projections)
        ];
    }

    protected function calculateCollectionTrends(array $projections): array
    {
        return [
            'collection_trend' => $this->calculateTrendDirection($projections, 'total_projected'),
            'efficiency_trend' => $this->calculateEfficiencyTrend($projections)
        ];
    }

    protected function getCurrentPeriodData(string $period, ?int $officeId = null): array
    {
        // Implementation depends on your specific business logic
        return [
            'revenue' => 0,
            'sales_count' => 0,
            'collections' => 0,
            'conversion_rate' => 0
        ];
    }

    protected function getPreviousPeriodData(string $period, ?int $officeId = null): array
    {
        // Implementation depends on your specific business logic
        return [
            'revenue' => 0,
            'sales_count' => 0,
            'collections' => 0,
            'conversion_rate' => 0
        ];
    }

    protected function getRevenueTarget(string $period, ?int $officeId = null): float
    {
        // Return target based on period and office
        return 100000; // Default target
    }

    protected function getSalesTarget(string $period, ?int $officeId = null): int
    {
        // Return sales target
        return 50; // Default target
    }

    protected function getCollectionTarget(string $period, ?int $officeId = null): float
    {
        // Return collection target
        return 80000; // Default target
    }

    protected function calculateAchievementRate(float $current, float $target): float
    {
        return $target > 0 ? round(($current / $target) * 100, 2) : 0;
    }

    protected function calculateGrowthRate(float $previous, float $current): float
    {
        return $previous > 0 ? round((($current - $previous) / $previous) * 100, 2) : 0;
    }

    protected function calculateProjectedGrowthRate(array $historicalData, array $projections): float
    {
        if (empty($historicalData) || empty($projections)) {
            return 0;
        }
        
        $lastHistorical = end($historicalData);
        $firstProjection = reset($projections);
        
        $historicalValue = $lastHistorical['revenue'] ?? $lastHistorical['sales_count'] ?? 0;
        $projectedValue = $firstProjection['projected_revenue'] ?? $firstProjection['projected_sales_count'] ?? 0;
        
        return $this->calculateGrowthRate($historicalValue, $projectedValue);
    }

    protected function calculateOverallConfidence(array $projections): float
    {
        if (empty($projections)) {
            return 0;
        }
        
        $confidenceLevels = array_column($projections, 'confidence_level');
        return array_sum($confidenceLevels) / count($confidenceLevels);
    }

    protected function calculateTrendDirection(array $data, string $field): string
    {
        if (count($data) < 2) {
            return 'stable';
        }
        
        $values = array_column($data, $field);
        $firstHalf = array_slice($values, 0, ceil(count($values) / 2));
        $secondHalf = array_slice($values, floor(count($values) / 2));
        
        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);
        
        $change = ($secondAvg - $firstAvg) / max($firstAvg, 1);
        
        if ($change > 0.05) return 'increasing';
        if ($change < -0.05) return 'decreasing';
        return 'stable';
    }

    protected function calculateVolatility(array $data, string $field): float
    {
        if (count($data) < 2) {
            return 0;
        }
        
        $values = array_column($data, $field);
        $mean = array_sum($values) / count($values);
        
        $squaredDiffs = array_map(function($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);
        
        $variance = array_sum($squaredDiffs) / count($squaredDiffs);
        $standardDeviation = sqrt($variance);
        
        return $mean > 0 ? round(($standardDeviation / $mean) * 100, 2) : 0;
    }

    protected function calculateSeasonalityImpact(array $projections): float
    {
        $adjustments = array_column($projections, 'seasonal_adjustment');
        return array_sum($adjustments) / count($adjustments);
    }

    protected function calculateEfficiencyTrend(array $projections): string
    {
        $rates = array_column($projections, 'collection_rate');
        
        if (count($rates) < 2) {
            return 'stable';
        }
        
        $firstHalf = array_slice($rates, 0, ceil(count($rates) / 2));
        $secondHalf = array_slice($rates, floor(count($rates) / 2));
        
        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);
        
        $change = $secondAvg - $firstAvg;
        
        if ($change > 2) return 'improving';
        if ($change < -2) return 'declining';
        return 'stable';
    }
}