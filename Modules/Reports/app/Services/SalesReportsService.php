<?php

namespace Modules\Reports\Services;

use Modules\Reports\Repositories\SalesRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SalesReportsService
{
    protected $salesRepository;

    public function __construct(SalesRepository $salesRepository)
    {
        $this->salesRepository = $salesRepository;
    }

    /**
     * Get dashboard data for sales reports
     */
    public function getDashboardData($dateFrom, $dateTo, $employeeId = null, $projectId = null)
    {
        // Set default dates if not provided - use a wider range to capture existing data
        if (!$dateFrom) {
            $dateFrom = Carbon::now()->subYear()->format('Y-m-d');
        }
        if (!$dateTo) {
            $dateTo = Carbon::now()->format('Y-m-d');
        }
        
        $cacheKey = "reports:sales_dashboard:v1:{$dateFrom}:{$dateTo}:employee={$employeeId}:project={$projectId}";

        return Cache::remember($cacheKey, 120, function () use ($dateFrom, $dateTo, $employeeId, $projectId) {
            try {
                $summary = $this->getSalesSummary($dateFrom, $dateTo, $employeeId, $projectId);
                $trends = $this->getSalesTrends($dateFrom, $dateTo, $employeeId, $projectId);
                $topPerformers = $this->getTopPerformers($dateFrom, $dateTo, $projectId);
                $conversionRates = $this->getConversionRates($dateFrom, $dateTo, $employeeId, $projectId);

                if (config('app.debug')) {
                    \Log::info('Dashboard data retrieved:', [
                        'summary' => $summary,
                        'trends' => $trends,
                        'top_performers' => $topPerformers,
                        'conversion_rates' => $conversionRates
                    ]);
                }

                return [
                    'summary' => $summary ?: [
                        'total_sales' => 0,
                        'total_revenue' => 0,
                        'average_sale' => 0,
                        'unique_clients' => 0,
                        'active_employees' => 0
                    ],
                    'trends' => $trends ?: [],
                    'top_performers' => $topPerformers ?: [],
                    'conversion_rates' => $conversionRates ?: [
                        'leads_to_prospects' => 0,
                        'prospects_to_sales' => 0,
                        'overall_conversion' => 0
                    ]
                ];
            } catch (\Exception $e) {
                \Log::error('Error in getDashboardData: ' . $e->getMessage());
                if (config('app.debug')) {
                    \Log::error('Stack trace: ' . $e->getTraceAsString());
                }
                return $this->getMockDashboardData();
            }
        });
    }

    /**
     * Get mock dashboard data when no real data exists
     */
    private function getMockDashboardData()
    {
        return [
            'summary' => [
                'total_sales' => 0,
                'total_revenue' => 0,
                'average_sale' => 0,
                'sales_growth' => 0
            ],
            'trends' => [],
            'top_performers' => [],
            'conversion_rates' => [
                'leads_to_prospects' => 0,
                'prospects_to_sales' => 0,
                'overall_conversion' => 0
            ]
        ];
    }

    /**
     * Get all sales with detailed information
     */
    public function getAllSales($dateFrom = null, $dateTo = null, $employeeId = null, $projectId = null, $limit = 100, $offset = 0)
    {
        // Set default dates if not provided
        if (!$dateFrom) {
            $dateFrom = Carbon::now()->subYear()->format('Y-m-d');
        }
        if (!$dateTo) {
            $dateTo = Carbon::now()->format('Y-m-d');
        }

        return $this->salesRepository->getAllSales($dateFrom, $dateTo, $employeeId, $projectId, $limit, $offset);
    }

    /**
     * Get sales by period
     */
    public function getSalesByPeriod($period, $dateFrom = null, $dateTo = null, $employeeId = null)
    {
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now()->subMonths(6);
        $dateTo = $dateTo ? Carbon::parse($dateTo) : Carbon::now();

        return $this->salesRepository->getSalesByPeriod($period, $dateFrom, $dateTo, $employeeId);
    }

    /**
     * Get sales performance by employee
     */
    public function getSalesPerformance($dateFrom = null, $dateTo = null, $department = null)
    {
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now()->startOfMonth();
        $dateTo = $dateTo ? Carbon::parse($dateTo) : Carbon::now()->endOfMonth();

        return $this->salesRepository->getSalesPerformance($dateFrom, $dateTo, $department);
    }

    /**
     * Get conversion funnel data
     */
    public function getConversionFunnel($dateFrom = null, $dateTo = null, $employeeId = null)
    {
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now()->startOfMonth();
        $dateTo = $dateTo ? Carbon::parse($dateTo) : Carbon::now()->endOfMonth();

        return $this->salesRepository->getConversionFunnel($dateFrom, $dateTo, $employeeId);
    }

    /**
     * Get top selling products/lots
     */
    public function getTopProducts($dateFrom = null, $dateTo = null, $limit = 10)
    {
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now()->startOfMonth();
        $dateTo = $dateTo ? Carbon::parse($dateTo) : Carbon::now()->endOfMonth();

        return $this->salesRepository->getTopProducts($dateFrom, $dateTo, $limit);
    }

    /**
     * Get sales summary
     */
    private function getSalesSummary($dateFrom, $dateTo, $employeeId, $projectId)
    {
        $result = $this->salesRepository->getSalesSummary($dateFrom, $dateTo, $employeeId, $projectId);
        \Log::info('getSalesSummary result:', ['result' => $result, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo]);
        return $result;
    }

    /**
     * Get sales trends
     */
    private function getSalesTrends($dateFrom, $dateTo, $employeeId, $projectId)
    {
        return $this->salesRepository->getSalesTrends($dateFrom, $dateTo, $employeeId, $projectId);
    }

    /**
     * Get top performers
     */
    private function getTopPerformers($dateFrom, $dateTo, $projectId)
    {
        return $this->salesRepository->getTopPerformers($dateFrom, $dateTo, $projectId);
    }

    /**
     * Get conversion rates
     */
    private function getConversionRates($dateFrom, $dateTo, $employeeId, $projectId)
    {
        return $this->salesRepository->getConversionRates($dateFrom, $dateTo, $employeeId, $projectId);
    }
}
