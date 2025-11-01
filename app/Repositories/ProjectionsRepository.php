<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProjectionsRepository
{
    /**
     * Get trend analysis for projections
     */
    public function getTrendAnalysis(string $metric, string $period, string $startDate, string $endDate, ?int $officeId = null): array
    {
        switch ($metric) {
            case 'revenue':
                return $this->getRevenueTrendAnalysis($period, $startDate, $endDate, $officeId);
            case 'sales':
                return $this->getSalesTrendAnalysis($period, $startDate, $endDate, $officeId);
            case 'collections':
                return $this->getCollectionsTrendAnalysis($period, $startDate, $endDate, $officeId);
            default:
                throw new \Exception('Métrica de tendencia no válida');
        }
    }

    /**
     * Get revenue trend analysis
     */
    protected function getRevenueTrendAnalysis(string $period, string $startDate, string $endDate, ?int $officeId = null): array
    {
        $dateFormat = $this->getDateFormat($period);
        
        $query = DB::table('contracts as c')
            ->selectRaw("
                {$dateFormat} as period,
                SUM(c.total_amount) as revenue,
                COUNT(*) as sales_count,
                AVG(c.total_amount) as avg_sale_value
            ")
            ->where('c.created_at', '>=', $startDate)
            ->where('c.created_at', '<=', $endDate)
            ->groupBy(DB::raw($dateFormat))
            ->orderBy('period');

        if ($officeId) {
            $query->where('c.office_id', $officeId);
        }

        $data = $query->get()->toArray();

        return [
            'data' => $data,
            'trend_direction' => $this->calculateTrendDirection($data, 'revenue'),
            'growth_rate' => $this->calculateGrowthRate($data, 'revenue'),
            'volatility' => $this->calculateVolatility($data, 'revenue'),
            'seasonality' => $this->detectSeasonality($data, 'revenue')
        ];
    }

    /**
     * Get sales trend analysis
     */
    protected function getSalesTrendAnalysis(string $period, string $startDate, string $endDate, ?int $officeId = null): array
    {
        $dateFormat = $this->getDateFormat($period);
        
        $query = DB::table('contracts as c')
            ->selectRaw("
                {$dateFormat} as period,
                COUNT(*) as sales_count,
                SUM(c.total_amount) as revenue
            ")
            ->where('c.created_at', '>=', $startDate)
            ->where('c.created_at', '<=', $endDate)
            ->groupBy(DB::raw($dateFormat))
            ->orderBy('period');

        if ($officeId) {
            $query->where('c.office_id', $officeId);
        }

        $data = $query->get()->toArray();

        return [
            'data' => $data,
            'trend_direction' => $this->calculateTrendDirection($data, 'sales_count'),
            'growth_rate' => $this->calculateGrowthRate($data, 'sales_count'),
            'volatility' => $this->calculateVolatility($data, 'sales_count'),
            'conversion_trends' => $this->getConversionTrends($startDate, $endDate, $officeId)
        ];
    }

    /**
     * Get collections trend analysis
     */
    protected function getCollectionsTrendAnalysis(string $period, string $startDate, string $endDate, ?int $officeId = null): array
    {
        $dateFormat = $this->getDateFormat($period);
        
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->selectRaw("
                {$dateFormat} as period,
                SUM(ps.paid_amount) as collections,
                SUM(ps.amount) as scheduled_amount,
                COUNT(*) as payment_count,
                (SUM(ps.paid_amount) / SUM(ps.amount)) * 100 as collection_rate
            ")
            ->where('ps.payment_date', '>=', $startDate)
            ->where('ps.payment_date', '<=', $endDate)
            ->where('ps.status', 'paid')
            ->groupBy(DB::raw($dateFormat))
            ->orderBy('period');

        if ($officeId) {
            $query->where('c.office_id', $officeId);
        }

        $data = $query->get()->toArray();

        return [
            'data' => $data,
            'trend_direction' => $this->calculateTrendDirection($data, 'collections'),
            'collection_rate_trend' => $this->calculateTrendDirection($data, 'collection_rate'),
            'efficiency_analysis' => $this->getCollectionEfficiencyAnalysis($data)
        ];
    }

    /**
     * Get historical data for baseline calculations
     */
    public function getHistoricalBaseline(string $metric, string $startDate, string $endDate, ?int $officeId = null): array
    {
        switch ($metric) {
            case 'revenue':
                return $this->getHistoricalRevenue($startDate, $endDate, $officeId);
            case 'sales':
                return $this->getHistoricalSales($startDate, $endDate, $officeId);
            case 'collections':
                return $this->getHistoricalCollections($startDate, $endDate, $officeId);
            default:
                return [];
        }
    }

    /**
     * Get seasonal patterns
     */
    public function getSeasonalPatterns(string $metric, int $years = 3, ?int $officeId = null): array
    {
        $startDate = Carbon::now()->subYears($years)->startOfYear()->format('Y-m-d');
        $endDate = Carbon::now()->endOfYear()->format('Y-m-d');

        switch ($metric) {
            case 'revenue':
                return $this->getRevenueSeasonalPatterns($startDate, $endDate, $officeId);
            case 'sales':
                return $this->getSalesSeasonalPatterns($startDate, $endDate, $officeId);
            case 'collections':
                return $this->getCollectionsSeasonalPatterns($startDate, $endDate, $officeId);
            default:
                return [];
        }
    }

    /**
     * Get market indicators that might affect projections
     */
    public function getMarketIndicators(): array
    {
        // This would typically connect to external APIs or internal market data
        // For now, return a structure that can be populated
        return [
            'economic_indicators' => [
                'interest_rates' => 0,
                'inflation_rate' => 0,
                'gdp_growth' => 0
            ],
            'real_estate_indicators' => [
                'market_inventory' => 0,
                'average_days_on_market' => 0,
                'price_trends' => 0
            ],
            'company_indicators' => [
                'marketing_spend' => 0,
                'lead_generation' => 0,
                'sales_team_size' => 0
            ]
        ];
    }

    // Helper methods

    protected function getDateFormat(string $period): string
    {
        switch ($period) {
            case 'daily':
                return 'DATE(created_at)';
            case 'weekly':
                return 'YEARWEEK(created_at)';
            case 'monthly':
                return 'DATE_FORMAT(created_at, "%Y-%m")';
            case 'quarterly':
                return 'CONCAT(YEAR(created_at), "-Q", QUARTER(created_at))';
            case 'yearly':
                return 'YEAR(created_at)';
            default:
                return 'DATE_FORMAT(created_at, "%Y-%m")';
        }
    }

    protected function calculateTrendDirection(array $data, string $field): string
    {
        if (count($data) < 2) {
            return 'stable';
        }

        $values = array_column($data, $field);
        $n = count($values);
        
        // Calculate linear regression slope
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

        if ($avgY == 0) {
            return 'stable';
        }

        $relativeSlope = $slope / $avgY;

        if ($relativeSlope > 0.05) {
            return 'increasing';
        } elseif ($relativeSlope < -0.05) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    protected function calculateGrowthRate(array $data, string $field): float
    {
        if (count($data) < 2) {
            return 0;
        }

        $values = array_column($data, $field);
        $firstValue = reset($values);
        $lastValue = end($values);

        if ($firstValue == 0) {
            return $lastValue > 0 ? 100 : 0;
        }

        return round((($lastValue - $firstValue) / $firstValue) * 100, 2);
    }

    protected function calculateVolatility(array $data, string $field): float
    {
        if (count($data) < 2) {
            return 0;
        }

        $values = array_column($data, $field);
        $mean = array_sum($values) / count($values);

        if ($mean == 0) {
            return 0;
        }

        $squaredDiffs = array_map(function($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);

        $variance = array_sum($squaredDiffs) / count($squaredDiffs);
        $standardDeviation = sqrt($variance);

        return round(($standardDeviation / $mean) * 100, 2);
    }

    protected function detectSeasonality(array $data, string $field): array
    {
        if (count($data) < 12) {
            return ['has_seasonality' => false, 'seasonal_factors' => []];
        }

        $values = array_column($data, $field);
        $periods = array_column($data, 'period');
        
        // Group by month to detect seasonal patterns
        $monthlyData = [];
        foreach ($data as $item) {
            $month = date('m', strtotime($item->period . '-01'));
            if (!isset($monthlyData[$month])) {
                $monthlyData[$month] = [];
            }
            $monthlyData[$month][] = $item->$field;
        }

        $seasonalFactors = [];
        $overallAverage = array_sum($values) / count($values);

        foreach ($monthlyData as $month => $monthValues) {
            $monthAverage = array_sum($monthValues) / count($monthValues);
            $seasonalFactors[$month] = $overallAverage > 0 ? $monthAverage / $overallAverage : 1;
        }

        // Check if there's significant seasonal variation
        $maxFactor = max($seasonalFactors);
        $minFactor = min($seasonalFactors);
        $hasSeasonality = ($maxFactor - $minFactor) > 0.2; // 20% variation threshold

        return [
            'has_seasonality' => $hasSeasonality,
            'seasonal_factors' => $seasonalFactors,
            'seasonal_strength' => $maxFactor - $minFactor
        ];
    }

    protected function getConversionTrends(string $startDate, string $endDate, ?int $officeId = null): array
    {
        // This would require leads/prospects data
        // Return mock structure for now
        return [
            'lead_to_prospect_trend' => 'stable',
            'prospect_to_sale_trend' => 'stable',
            'overall_conversion_trend' => 'stable'
        ];
    }

    protected function getCollectionEfficiencyAnalysis(array $data): array
    {
        if (empty($data)) {
            return [
                'efficiency_trend' => 'stable',
                'average_collection_rate' => 0,
                'efficiency_volatility' => 0
            ];
        }

        $collectionRates = array_column($data, 'collection_rate');
        $averageRate = array_sum($collectionRates) / count($collectionRates);
        
        return [
            'efficiency_trend' => $this->calculateTrendDirection($data, 'collection_rate'),
            'average_collection_rate' => round($averageRate, 2),
            'efficiency_volatility' => $this->calculateVolatility($data, 'collection_rate')
        ];
    }

    protected function getHistoricalRevenue(string $startDate, string $endDate, ?int $officeId = null): array
    {
        $query = DB::table('contracts as c')
            ->selectRaw('
                DATE_FORMAT(c.created_at, "%Y-%m") as period,
                SUM(c.total_amount) as revenue,
                COUNT(*) as sales_count,
                AVG(c.total_amount) as avg_sale_value
            ')
            ->where('c.created_at', '>=', $startDate)
            ->where('c.created_at', '<=', $endDate)
            ->groupBy(DB::raw('DATE_FORMAT(c.created_at, "%Y-%m")'))
            ->orderBy('period');

        if ($officeId) {
            $query->where('c.office_id', $officeId);
        }

        return $query->get()->toArray();
    }

    protected function getHistoricalSales(string $startDate, string $endDate, ?int $officeId = null): array
    {
        $query = DB::table('contracts as c')
            ->selectRaw('
                DATE_FORMAT(c.created_at, "%Y-%m") as period,
                COUNT(*) as sales_count,
                SUM(c.total_amount) as revenue
            ')
            ->where('c.created_at', '>=', $startDate)
            ->where('c.created_at', '<=', $endDate)
            ->groupBy(DB::raw('DATE_FORMAT(c.created_at, "%Y-%m")'))
            ->orderBy('period');

        if ($officeId) {
            $query->where('c.office_id', $officeId);
        }

        return $query->get()->toArray();
    }

    protected function getHistoricalCollections(string $startDate, string $endDate, ?int $officeId = null): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->selectRaw('
                DATE_FORMAT(ps.payment_date, "%Y-%m") as period,
                SUM(ps.paid_amount) as collections,
                COUNT(*) as payment_count
            ')
            ->where('ps.payment_date', '>=', $startDate)
            ->where('ps.payment_date', '<=', $endDate)
            ->where('ps.status', 'paid')
            ->groupBy(DB::raw('DATE_FORMAT(ps.payment_date, "%Y-%m")'))
            ->orderBy('period');

        if ($officeId) {
            $query->where('c.office_id', $officeId);
        }

        return $query->get()->toArray();
    }

    protected function getRevenueSeasonalPatterns(string $startDate, string $endDate, ?int $officeId = null): array
    {
        $query = DB::table('contracts as c')
            ->selectRaw('
                MONTH(c.created_at) as month,
                MONTHNAME(c.created_at) as month_name,
                AVG(SUM(c.total_amount)) OVER (PARTITION BY MONTH(c.created_at)) as avg_monthly_revenue,
                COUNT(*) as data_points
            ')
            ->where('c.created_at', '>=', $startDate)
            ->where('c.created_at', '<=', $endDate)
            ->groupBy(DB::raw('MONTH(c.created_at)'), DB::raw('MONTHNAME(c.created_at)'))
            ->orderBy('month');

        if ($officeId) {
            $query->where('c.office_id', $officeId);
        }

        return $query->get()->toArray();
    }

    protected function getSalesSeasonalPatterns(string $startDate, string $endDate, ?int $officeId = null): array
    {
        $query = DB::table('contracts as c')
            ->selectRaw('
                MONTH(c.created_at) as month,
                MONTHNAME(c.created_at) as month_name,
                AVG(COUNT(*)) OVER (PARTITION BY MONTH(c.created_at)) as avg_monthly_sales,
                COUNT(*) as data_points
            ')
            ->where('c.created_at', '>=', $startDate)
            ->where('c.created_at', '<=', $endDate)
            ->groupBy(DB::raw('MONTH(c.created_at)'), DB::raw('MONTHNAME(c.created_at)'))
            ->orderBy('month');

        if ($officeId) {
            $query->where('c.office_id', $officeId);
        }

        return $query->get()->toArray();
    }

    protected function getCollectionsSeasonalPatterns(string $startDate, string $endDate, ?int $officeId = null): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->selectRaw('
                MONTH(ps.payment_date) as month,
                MONTHNAME(ps.payment_date) as month_name,
                AVG(SUM(ps.paid_amount)) OVER (PARTITION BY MONTH(ps.payment_date)) as avg_monthly_collections,
                COUNT(*) as data_points
            ')
            ->where('ps.payment_date', '>=', $startDate)
            ->where('ps.payment_date', '<=', $endDate)
            ->where('ps.status', 'paid')
            ->groupBy(DB::raw('MONTH(ps.payment_date)'), DB::raw('MONTHNAME(ps.payment_date)'))
            ->orderBy('month');

        if ($officeId) {
            $query->where('c.office_id', $officeId);
        }

        return $query->get()->toArray();
    }
}