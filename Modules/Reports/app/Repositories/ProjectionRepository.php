<?php

namespace Modules\Reports\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProjectionRepository
{
    /**
     * Get monthly revenue data for projection calculations
     * Groups historical sales by month and calculates totals
     */
    public function getMonthlyRevenue($monthsBack = 12)
    {
        $startDate = Carbon::now()->subMonths($monthsBack)->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        $results = DB::table('contracts as c')
            ->whereBetween('c.sign_date', [$startDate, $endDate])
            ->where('c.status', 'vigente')
            ->selectRaw('
                YEAR(c.sign_date) as year,
                MONTH(c.sign_date) as month,
                DATE_FORMAT(c.sign_date, "%Y-%m") as period,
                COUNT(*) as sales_count,
                SUM(c.total_price) as total_revenue,
                AVG(c.total_price) as avg_sale_value,
                SUM(c.down_payment) as total_down_payments,
                SUM(c.financing_amount) as total_financing
            ')
            ->groupBy('year', 'month', 'period')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        $monthNames = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
        ];

        return $results->map(function($item) use ($monthNames) {
            return [
                'year' => (int)$item->year,
                'month' => (int)$item->month,
                'period' => $item->period,
                'month_label' => "{$monthNames[$item->month]} {$item->year}",
                'sales_count' => (int)$item->sales_count,
                'total_revenue' => (float)$item->total_revenue,
                'avg_sale_value' => (float)$item->avg_sale_value,
                'total_down_payments' => (float)$item->total_down_payments,
                'total_financing' => (float)$item->total_financing,
            ];
        })->toArray();
    }
    
    /**
     * DEPRECATED: Use getMonthlyRevenue() instead
     * Kept for backwards compatibility
     */
    public function getQuarterlyRevenue($yearsBack = 2)
    {
        return $this->getMonthlyRevenue($yearsBack * 12);
    }

    /**
     * Calculate linear regression for revenue projection
     * Formula: y = mx + b
     * Where x = quarter index, y = revenue
     */
    public function calculateLinearRegression($quarterlyData)
    {
        $n = count($quarterlyData);
        if ($n < 2) {
            return ['slope' => 0, 'intercept' => 0, 'r_squared' => 0];
        }

        // Prepare data: x = quarter index (0, 1, 2...), y = revenue
        $x = range(0, $n - 1);
        $y = array_column($quarterlyData, 'total_revenue');

        // Calculate means
        $x_mean = array_sum($x) / $n;
        $y_mean = array_sum($y) / $n;

        // Calculate slope (m) and intercept (b)
        $numerator = 0;
        $denominator = 0;
        $ss_total = 0;
        $ss_residual = 0;

        for ($i = 0; $i < $n; $i++) {
            $numerator += ($x[$i] - $x_mean) * ($y[$i] - $y_mean);
            $denominator += pow($x[$i] - $x_mean, 2);
            $ss_total += pow($y[$i] - $y_mean, 2);
        }

        $slope = $denominator != 0 ? $numerator / $denominator : 0;
        $intercept = $y_mean - ($slope * $x_mean);

        // Calculate R² (coefficient of determination)
        for ($i = 0; $i < $n; $i++) {
            $predicted = $slope * $x[$i] + $intercept;
            $ss_residual += pow($y[$i] - $predicted, 2);
        }
        $r_squared = $ss_total != 0 ? 1 - ($ss_residual / $ss_total) : 0;

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => $r_squared
        ];
    }

    /**
     * Project future months using linear regression
     */
    public function projectFutureMonths($monthlyData, $monthsAhead = 6)
    {
        $regression = $this->calculateLinearRegression($monthlyData);
        $n = count($monthlyData);
        $projections = [];

        $monthNames = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
        ];

        // Get the last month from data
        $lastMonth = end($monthlyData);
        $lastYear = $lastMonth['year'];
        $lastM = $lastMonth['month'];
        $lastRevenue = $lastMonth['total_revenue'];

        // Calculate average revenue for baseline (when we have few data points)
        $avgRevenue = array_sum(array_column($monthlyData, 'total_revenue')) / count($monthlyData);
        
        // Use conservative projection if:
        // 1. We have very few data points (< 3)
        // 2. The trend is highly negative (slope < -avgRevenue/12)
        $useFallback = ($n < 3) || ($regression['slope'] < -($avgRevenue / 12));

        for ($i = 1; $i <= $monthsAhead; $i++) {
            $monthIndex = $n + $i - 1;
            
            if ($useFallback) {
                // Use last month value with slight decay for conservative estimate
                // This prevents projecting 0 when we have insufficient data
                $projectedRevenue = $lastRevenue * (1 - ($i * 0.05)); // 5% decay per month
                $projectedRevenue = max($lastRevenue * 0.50, $projectedRevenue); // Never go below 50% of last value
            } else {
                // Use linear regression for projection
                $projectedRevenue = ($regression['slope'] * $monthIndex) + $regression['intercept'];
            }
            
            // Ensure non-negative projection
            $projectedRevenue = max(0, $projectedRevenue);

            // Calculate next month
            $nextM = $lastM + $i;
            $nextYear = $lastYear + floor(($nextM - 1) / 12);
            $nextMonth = (($nextM - 1) % 12) + 1;

            // Adjust confidence based on data quality
            $confidence = $useFallback ? max(0.3, $regression['r_squared'] * 0.5) : $regression['r_squared'];

            $projections[] = [
                'year' => $nextYear,
                'month' => $nextMonth,
                'period' => sprintf("%d-%02d", $nextYear, $nextMonth),
                'month_label' => "{$monthNames[$nextMonth]} {$nextYear}",
                'projected_revenue' => round($projectedRevenue, 2),
                'confidence' => $confidence, // Adjusted confidence
                'is_projection' => true,
                'projection_method' => $useFallback ? 'conservative' : 'linear_regression'
            ];
        }

        return $projections;
    }
    
    /**
     * DEPRECATED: Use projectFutureMonths() instead
     */
    public function projectFutureQuarters($quarterlyData, $quartersAhead = 4)
    {
        return $this->projectFutureMonths($quarterlyData, $quartersAhead * 3);
    }

    /**
     * Calculate growth rate between periods (works with monthly or quarterly data)
     */
    public function calculateGrowthRates($periodData)
    {
        $rates = [];
        
        for ($i = 1; $i < count($periodData); $i++) {
            $current = $periodData[$i]['total_revenue'];
            $previous = $periodData[$i - 1]['total_revenue'];
            
            $growthRate = $previous != 0 
                ? (($current - $previous) / $previous) * 100 
                : 0;

            // Support both monthly and quarterly data
            $label = $periodData[$i]['month_label'] ?? $periodData[$i]['quarter_label'] ?? 'Period ' . $i;

            $rates[] = [
                'period_label' => $label,
                'month_label' => $periodData[$i]['month_label'] ?? null,
                'quarter_label' => $periodData[$i]['quarter_label'] ?? null,
                'growth_rate' => round($growthRate, 2),
                'previous_revenue' => $previous,
                'current_revenue' => $current,
                'absolute_change' => $current - $previous
            ];
        }

        // Calculate average growth rate
        if (!empty($rates)) {
            $avgGrowthRate = array_sum(array_column($rates, 'growth_rate')) / count($rates);
        } else {
            $avgGrowthRate = 0;
        }

        return [
            'quarterly_growth' => $rates, // Keep key name for backwards compatibility
            'average_growth_rate' => round($avgGrowthRate, 2)
        ];
    }

    /**
     * Detect seasonal patterns (works with monthly or quarterly data)
     */
    public function detectSeasonality($periodData)
    {
        // Detect if it's monthly or quarterly data
        $isMonthly = isset($periodData[0]['month']) && !isset($periodData[0]['quarter']);
        
        if ($isMonthly) {
            // Monthly seasonality (12 months)
            $monthNames = [
                1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 
                5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
            ];
            $monthAverages = array_fill(1, 12, []);

            foreach ($periodData as $data) {
                $m = $data['month'];
                $monthAverages[$m][] = $data['total_revenue'];
            }

            $seasonalFactors = [];
            $overallAvg = array_sum(array_column($periodData, 'total_revenue')) / count($periodData);

            foreach ($monthAverages as $m => $revenues) {
                if (!empty($revenues)) {
                    $avg = array_sum($revenues) / count($revenues);
                    $seasonalFactors[$monthNames[$m]] = $overallAvg != 0 ? $avg / $overallAvg : 1;
                } else {
                    $seasonalFactors[$monthNames[$m]] = 1;
                }
            }
        } else {
            // Quarterly seasonality (4 quarters)
            $quarterAverages = [1 => [], 2 => [], 3 => [], 4 => []];

            foreach ($periodData as $data) {
                $q = $data['quarter'];
                $quarterAverages[$q][] = $data['total_revenue'];
            }

            $seasonalFactors = [];
            $overallAvg = array_sum(array_column($periodData, 'total_revenue')) / count($periodData);

            foreach ($quarterAverages as $q => $revenues) {
                if (!empty($revenues)) {
                    $avg = array_sum($revenues) / count($revenues);
                    $seasonalFactors["Q$q"] = $overallAvg != 0 ? $avg / $overallAvg : 1;
                } else {
                    $seasonalFactors["Q$q"] = 1;
                }
            }
        }

        return $seasonalFactors;
    }

    /**
     * Get current month revenue (actual performance)
     */
    public function getCurrentMonthRevenue()
    {
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();
        $today = Carbon::now();

        $actual = DB::table('contracts as c')
            ->whereBetween('c.sign_date', [$startDate, $today])
            ->where('c.status', 'vigente')
            ->selectRaw('
                COUNT(*) as sales_count,
                SUM(c.total_price) as total_revenue,
                AVG(c.total_price) as avg_sale_value
            ')
            ->first();

        $monthNames = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
        ];

        // Calculate days elapsed and remaining
        $daysInMonth = $startDate->diffInDays($endDate) + 1;
        $daysElapsed = $startDate->diffInDays($today) + 1;
        $daysRemaining = $today->diffInDays($endDate);

        // Project current month to end (simple extrapolation)
        $dailyRate = $daysElapsed > 0 ? $actual->total_revenue / $daysElapsed : 0;
        $projectedMonthEnd = $actual->total_revenue + ($dailyRate * $daysRemaining);

        return [
            'year' => $today->year,
            'month' => $today->month,
            'period' => $today->format('Y-m'),
            'month_label' => "{$monthNames[$today->month]} {$today->year}",
            'actual_revenue' => (float)$actual->total_revenue,
            'sales_count' => (int)$actual->sales_count,
            'avg_sale_value' => (float)$actual->avg_sale_value,
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            'days_in_month' => $daysInMonth,
            'progress_percentage' => round(($daysElapsed / $daysInMonth) * 100, 2),
            'projected_month_end' => round($projectedMonthEnd, 2),
            'daily_rate' => round($dailyRate, 2)
        ];
    }
    
    /**
     * DEPRECATED: Use getCurrentMonthRevenue() instead
     */
    public function getCurrentQuarterRevenue()
    {
        return $this->getCurrentMonthRevenue();
    }
}
