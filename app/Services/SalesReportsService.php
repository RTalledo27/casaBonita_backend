<?php

namespace App\Services;

use App\Repositories\SalesRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SalesReportsService
{
    protected $salesRepository;

    public function __construct(SalesRepository $salesRepository)
    {
        $this->salesRepository = $salesRepository;
    }

    /**
     * Get sales report with filters and pagination
     */
    public function getSalesReport(array $filters, int $page = 1, int $perPage = 15): array
    {
        $cacheKey = 'sales_report_' . md5(serialize($filters) . $page . $perPage);
        
        return Cache::remember($cacheKey, 300, function () use ($filters, $page, $perPage) {
            $salesData = $this->salesRepository->getSalesWithFilters($filters, $page, $perPage);
            $summary = $this->calculateSalesSummary($salesData['data']);

            return [
                'data' => $salesData['data'],
                'summary' => $summary,
                'pagination' => $salesData['pagination']
            ];
        });
    }

    /**
     * Get sales summary metrics
     */
    public function getSalesSummary(array $filters): array
    {
        $cacheKey = 'sales_summary_' . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 300, function () use ($filters) {
            return $this->salesRepository->getSalesMetrics(
                $filters['start_date'],
                $filters['end_date'],
                $filters['advisor_id'] ?? null,
                $filters['office_id'] ?? null
            );
        });
    }

    /**
     * Get sales grouped by advisor
     */
    public function getSalesByAdvisor(array $filters): array
    {
        $cacheKey = 'sales_by_advisor_' . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 300, function () use ($filters) {
            return $this->salesRepository->getSalesByAdvisor(
                $filters['start_date'],
                $filters['end_date'],
                $filters['office_id'] ?? null
            );
        });
    }

    /**
     * Get sales trends and analytics
     */
    public function getSalesTrends(array $filters, string $period = 'monthly'): array
    {
        $cacheKey = 'sales_trends_' . md5(serialize($filters) . $period);
        
        return Cache::remember($cacheKey, 600, function () use ($filters, $period) {
            return $this->salesRepository->getSalesTrends(
                $filters['start_date'],
                $filters['end_date'],
                $period,
                $filters['office_id'] ?? null
            );
        });
    }

    /**
     * Calculate sales summary from data
     */
    protected function calculateSalesSummary(array $salesData): array
    {
        if (empty($salesData)) {
            return [
                'total_sales' => 0,
                'total_amount' => 0,
                'average_sale' => 0,
                'confirmed_sales' => 0,
                'pending_sales' => 0,
                'cancelled_sales' => 0
            ];
        }

        $totalSales = count($salesData);
        $totalAmount = array_sum(array_column($salesData, 'total_amount'));
        $averageSale = $totalSales > 0 ? $totalAmount / $totalSales : 0;

        $statusCounts = array_count_values(array_column($salesData, 'status'));

        return [
            'total_sales' => $totalSales,
            'total_amount' => round($totalAmount, 2),
            'average_sale' => round($averageSale, 2),
            'confirmed_sales' => $statusCounts['confirmed'] ?? 0,
            'pending_sales' => $statusCounts['pending'] ?? 0,
            'cancelled_sales' => $statusCounts['cancelled'] ?? 0
        ];
    }

    /**
     * Get top performing advisors
     */
    public function getTopAdvisors(array $filters, int $limit = 10): array
    {
        $cacheKey = 'top_advisors_' . md5(serialize($filters) . $limit);
        
        return Cache::remember($cacheKey, 600, function () use ($filters, $limit) {
            return $this->salesRepository->getTopAdvisors(
                $filters['start_date'],
                $filters['end_date'],
                $filters['office_id'] ?? null,
                $limit
            );
        });
    }

    /**
     * Get sales conversion metrics
     */
    public function getConversionMetrics(array $filters): array
    {
        $cacheKey = 'conversion_metrics_' . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 600, function () use ($filters) {
            return $this->salesRepository->getConversionMetrics(
                $filters['start_date'],
                $filters['end_date'],
                $filters['office_id'] ?? null
            );
        });
    }

    /**
     * Get sales by product/lot type
     */
    public function getSalesByProduct(array $filters): array
    {
        $cacheKey = 'sales_by_product_' . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 600, function () use ($filters) {
            return $this->salesRepository->getSalesByProduct(
                $filters['start_date'],
                $filters['end_date'],
                $filters['office_id'] ?? null
            );
        });
    }

    /**
     * Get monthly sales comparison
     */
    public function getMonthlySalesComparison(array $filters): array
    {
        $cacheKey = 'monthly_comparison_' . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 600, function () use ($filters) {
            $currentPeriod = $this->getSalesSummary($filters);
            
            // Calculate previous period
            $startDate = Carbon::parse($filters['start_date']);
            $endDate = Carbon::parse($filters['end_date']);
            $daysDiff = $startDate->diffInDays($endDate);
            
            $previousFilters = $filters;
            $previousFilters['start_date'] = $startDate->copy()->subDays($daysDiff + 1)->format('Y-m-d');
            $previousFilters['end_date'] = $startDate->copy()->subDay()->format('Y-m-d');
            
            $previousPeriod = $this->getSalesSummary($previousFilters);
            
            return [
                'current' => $currentPeriod,
                'previous' => $previousPeriod,
                'growth' => [
                    'sales_count' => $this->calculateGrowthRate(
                        $previousPeriod['total_sales'],
                        $currentPeriod['total_sales']
                    ),
                    'total_amount' => $this->calculateGrowthRate(
                        $previousPeriod['total_amount'],
                        $currentPeriod['total_amount']
                    ),
                    'average_sale' => $this->calculateGrowthRate(
                        $previousPeriod['average_sale'],
                        $currentPeriod['average_sale']
                    )
                ]
            ];
        });
    }

    /**
     * Calculate growth rate percentage
     */
    protected function calculateGrowthRate(float $previous, float $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        
        return round((($current - $previous) / $previous) * 100, 2);
    }
}