<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesRepository
{
    /**
     * Get sales report with filters and pagination
     */
    public function getSalesReport(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $query = DB::table('contracts as c')
            ->leftJoin('lots as l', 'c.lot_id', '=', 'l.id')
            ->leftJoin('projects as p', 'l.project_id', '=', 'p.id')
            ->leftJoin('users as advisor', 'c.advisor_id', '=', 'advisor.id')
            ->leftJoin('offices as o', 'c.office_id', '=', 'o.id')
            ->select([
                'c.id as contract_id',
                'c.contract_number',
                'c.client_name',
                'c.total_amount',
                'c.down_payment',
                'c.financing_amount',
                'c.created_at as sale_date',
                'c.status',
                'l.lot_number',
                'l.area',
                'p.name as project_name',
                'advisor.name as advisor_name',
                'o.name as office_name'
            ]);

        // Apply filters
        if (!empty($filters['start_date'])) {
            $query->where('c.created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('c.created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['advisor_id'])) {
            $query->where('c.advisor_id', $filters['advisor_id']);
        }

        if (!empty($filters['office_id'])) {
            $query->where('c.office_id', $filters['office_id']);
        }

        if (!empty($filters['project_id'])) {
            $query->where('l.project_id', $filters['project_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('c.status', $filters['status']);
        }

        if (!empty($filters['min_amount'])) {
            $query->where('c.total_amount', '>=', $filters['min_amount']);
        }

        if (!empty($filters['max_amount'])) {
            $query->where('c.total_amount', '<=', $filters['max_amount']);
        }

        // Get total count for pagination
        $totalCount = $query->count();

        // Apply pagination
        $offset = ($page - 1) * $perPage;
        $results = $query->orderBy('c.created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'data' => $results,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $perPage)
            ]
        ];
    }

    /**
     * Get sales summary metrics
     */
    public function getSalesSummary(array $filters = []): array
    {
        $query = DB::table('contracts as c')
            ->leftJoin('lots as l', 'c.lot_id', '=', 'l.id')
            ->leftJoin('projects as p', 'l.project_id', '=', 'p.id');

        // Apply filters
        $this->applyFilters($query, $filters);

        $summary = $query->selectRaw('
            COUNT(*) as total_sales,
            SUM(c.total_amount) as total_revenue,
            AVG(c.total_amount) as average_sale_value,
            SUM(c.down_payment) as total_down_payments,
            SUM(c.financing_amount) as total_financing
        ')->first();

        // Get status distribution
        $statusQuery = clone $query;
        $statusDistribution = $statusQuery->select('c.status', DB::raw('COUNT(*) as count'))
            ->groupBy('c.status')
            ->get()
            ->keyBy('status')
            ->toArray();

        // Get monthly trend (last 12 months)
        $monthlyTrend = $this->getMonthlySalesTrend($filters);

        return [
            'summary' => [
                'total_sales' => (int) $summary->total_sales,
                'total_revenue' => (float) $summary->total_revenue,
                'average_sale_value' => (float) $summary->average_sale_value,
                'total_down_payments' => (float) $summary->total_down_payments,
                'total_financing' => (float) $summary->total_financing
            ],
            'status_distribution' => $statusDistribution,
            'monthly_trend' => $monthlyTrend
        ];
    }

    /**
     * Get sales by advisor
     */
    public function getSalesByAdvisor(array $filters = []): array
    {
        $query = DB::table('contracts as c')
            ->leftJoin('users as advisor', 'c.advisor_id', '=', 'advisor.id')
            ->leftJoin('offices as o', 'c.office_id', '=', 'o.id');

        $this->applyFilters($query, $filters);

        return $query->select([
                'advisor.id as advisor_id',
                'advisor.name as advisor_name',
                'o.name as office_name',
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(c.total_amount) as total_revenue'),
                DB::raw('AVG(c.total_amount) as average_sale_value'),
                DB::raw('SUM(c.down_payment) as total_down_payments')
            ])
            ->groupBy('advisor.id', 'advisor.name', 'o.name')
            ->orderBy('total_revenue', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get sales trends and analytics
     */
    public function getSalesTrends(array $filters = []): array
    {
        // Monthly sales trend
        $monthlyTrend = $this->getMonthlySalesTrend($filters);
        
        // Weekly sales trend (last 12 weeks)
        $weeklyTrend = $this->getWeeklySalesTrend($filters);
        
        // Sales by project
        $projectSales = $this->getSalesByProject($filters);
        
        // Sales by price range
        $priceRanges = $this->getSalesByPriceRange($filters);

        return [
            'monthly_trend' => $monthlyTrend,
            'weekly_trend' => $weeklyTrend,
            'project_distribution' => $projectSales,
            'price_range_distribution' => $priceRanges
        ];
    }

    /**
     * Get monthly revenue data for projections
     */
    public function getMonthlyRevenue(string $startDate, string $endDate, ?int $officeId = null): array
    {
        $query = DB::table('contracts as c')
            ->selectRaw('
                DATE_FORMAT(c.created_at, "%Y-%m") as period,
                SUM(c.total_amount) as revenue,
                COUNT(*) as sales_count
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

    /**
     * Get monthly sales data for projections
     */
    public function getMonthlySalesData(string $startDate, string $endDate, ?int $officeId = null, ?int $advisorId = null): array
    {
        $query = DB::table('contracts as c')
            ->selectRaw('
                DATE_FORMAT(c.created_at, "%Y-%m") as period,
                COUNT(*) as sales_count,
                SUM(c.total_amount) as revenue,
                AVG(c.total_amount) as average_value
            ')
            ->where('c.created_at', '>=', $startDate)
            ->where('c.created_at', '<=', $endDate)
            ->groupBy(DB::raw('DATE_FORMAT(c.created_at, "%Y-%m")'))
            ->orderBy('period');

        if ($officeId) {
            $query->where('c.office_id', $officeId);
        }

        if ($advisorId) {
            $query->where('c.advisor_id', $advisorId);
        }

        return $query->get()->toArray();
    }

    /**
     * Get average monthly revenue
     */
    public function getAverageMonthlyRevenue(string $startDate, string $endDate, ?int $officeId = null): float
    {
        $query = DB::table('contracts as c')
            ->selectRaw('AVG(monthly_revenue) as avg_revenue')
            ->fromSub(function ($subQuery) use ($startDate, $endDate, $officeId) {
                $subQuery->from('contracts as c')
                    ->selectRaw('SUM(c.total_amount) as monthly_revenue')
                    ->where('c.created_at', '>=', $startDate)
                    ->where('c.created_at', '<=', $endDate)
                    ->groupBy(DB::raw('DATE_FORMAT(c.created_at, "%Y-%m")'));
                
                if ($officeId) {
                    $subQuery->where('c.office_id', $officeId);
                }
            }, 'monthly_data');

        $result = $query->first();
        return (float) ($result->avg_revenue ?? 0);
    }

    /**
     * Get top performing advisors
     */
    public function getTopAdvisors(array $filters = [], int $limit = 10): array
    {
        $query = DB::table('contracts as c')
            ->leftJoin('users as advisor', 'c.advisor_id', '=', 'advisor.id')
            ->leftJoin('offices as o', 'c.office_id', '=', 'o.id');

        $this->applyFilters($query, $filters);

        return $query->select([
                'advisor.id as advisor_id',
                'advisor.name as advisor_name',
                'o.name as office_name',
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(c.total_amount) as total_revenue'),
                DB::raw('AVG(c.total_amount) as average_sale_value')
            ])
            ->groupBy('advisor.id', 'advisor.name', 'o.name')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get conversion metrics
     */
    public function getConversionMetrics(array $filters = []): array
    {
        // This would require leads/prospects data
        // For now, return mock structure
        return [
            'leads_count' => 0,
            'prospects_count' => 0,
            'sales_count' => 0,
            'lead_to_prospect_rate' => 0,
            'prospect_to_sale_rate' => 0,
            'overall_conversion_rate' => 0
        ];
    }

    /**
     * Get sales by product/project
     */
    public function getSalesByProduct(array $filters = []): array
    {
        $query = DB::table('contracts as c')
            ->leftJoin('lots as l', 'c.lot_id', '=', 'l.id')
            ->leftJoin('projects as p', 'l.project_id', '=', 'p.id');

        $this->applyFilters($query, $filters);

        return $query->select([
                'p.id as project_id',
                'p.name as project_name',
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(c.total_amount) as total_revenue'),
                DB::raw('AVG(c.total_amount) as average_sale_value')
            ])
            ->groupBy('p.id', 'p.name')
            ->orderBy('total_revenue', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get monthly sales comparison
     */
    public function getMonthlySalesComparison(string $currentMonth, string $previousMonth, ?int $officeId = null): array
    {
        $currentData = $this->getMonthlySalesData($currentMonth . '-01', $currentMonth . '-31', $officeId);
        $previousData = $this->getMonthlySalesData($previousMonth . '-01', $previousMonth . '-31', $officeId);

        $current = $currentData[0] ?? ['sales_count' => 0, 'revenue' => 0];
        $previous = $previousData[0] ?? ['sales_count' => 0, 'revenue' => 0];

        return [
            'current' => $current,
            'previous' => $previous,
            'growth' => [
                'sales_count' => $this->calculateGrowthRate($previous['sales_count'], $current['sales_count']),
                'revenue' => $this->calculateGrowthRate($previous['revenue'], $current['revenue'])
            ]
        ];
    }

    // Helper methods

    protected function applyFilters($query, array $filters): void
    {
        if (!empty($filters['start_date'])) {
            $query->where('c.created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('c.created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['advisor_id'])) {
            $query->where('c.advisor_id', $filters['advisor_id']);
        }

        if (!empty($filters['office_id'])) {
            $query->where('c.office_id', $filters['office_id']);
        }

        if (!empty($filters['project_id'])) {
            $query->whereExists(function ($subQuery) use ($filters) {
                $subQuery->select(DB::raw(1))
                    ->from('lots as l')
                    ->whereRaw('l.id = c.lot_id')
                    ->where('l.project_id', $filters['project_id']);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('c.status', $filters['status']);
        }
    }

    protected function getMonthlySalesTrend(array $filters = []): array
    {
        $query = DB::table('contracts as c')
            ->selectRaw('
                DATE_FORMAT(c.created_at, "%Y-%m") as period,
                DATE_FORMAT(c.created_at, "%M %Y") as period_name,
                COUNT(*) as sales_count,
                SUM(c.total_amount) as revenue
            ')
            ->where('c.created_at', '>=', Carbon::now()->subMonths(12)->startOfMonth())
            ->groupBy(DB::raw('DATE_FORMAT(c.created_at, "%Y-%m")'))
            ->orderBy('period');

        $this->applyFilters($query, $filters);

        return $query->get()->toArray();
    }

    protected function getWeeklySalesTrend(array $filters = []): array
    {
        $query = DB::table('contracts as c')
            ->selectRaw('
                YEARWEEK(c.created_at) as week_period,
                DATE_FORMAT(c.created_at, "%Y-W%u") as week_name,
                COUNT(*) as sales_count,
                SUM(c.total_amount) as revenue
            ')
            ->where('c.created_at', '>=', Carbon::now()->subWeeks(12)->startOfWeek())
            ->groupBy(DB::raw('YEARWEEK(c.created_at)'))
            ->orderBy('week_period');

        $this->applyFilters($query, $filters);

        return $query->get()->toArray();
    }

    protected function getSalesByProject(array $filters = []): array
    {
        $query = DB::table('contracts as c')
            ->leftJoin('lots as l', 'c.lot_id', '=', 'l.id')
            ->leftJoin('projects as p', 'l.project_id', '=', 'p.id');

        $this->applyFilters($query, $filters);

        return $query->select([
                'p.name as project_name',
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(c.total_amount) as revenue')
            ])
            ->groupBy('p.name')
            ->orderBy('revenue', 'desc')
            ->get()
            ->toArray();
    }

    protected function getSalesByPriceRange(array $filters = []): array
    {
        $query = DB::table('contracts as c');
        $this->applyFilters($query, $filters);

        return $query->selectRaw('
                CASE 
                    WHEN c.total_amount < 50000 THEN "0-50K"
                    WHEN c.total_amount < 100000 THEN "50K-100K"
                    WHEN c.total_amount < 200000 THEN "100K-200K"
                    WHEN c.total_amount < 500000 THEN "200K-500K"
                    ELSE "500K+"
                END as price_range,
                COUNT(*) as sales_count,
                SUM(c.total_amount) as revenue
            ')
            ->groupBy(DB::raw('
                CASE 
                    WHEN c.total_amount < 50000 THEN "0-50K"
                    WHEN c.total_amount < 100000 THEN "50K-100K"
                    WHEN c.total_amount < 200000 THEN "100K-200K"
                    WHEN c.total_amount < 500000 THEN "200K-500K"
                    ELSE "500K+"
                END
            '))
            ->orderBy(DB::raw('MIN(c.total_amount)'))
            ->get()
            ->toArray();
    }

    protected function calculateGrowthRate(float $previous, float $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        
        return round((($current - $previous) / $previous) * 100, 2);
    }
}