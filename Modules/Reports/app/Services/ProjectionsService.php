<?php

namespace Modules\Reports\Services;

use Modules\Reports\Repositories\ProjectionsRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProjectionsService
{
    protected $projectionsRepository;

    public function __construct(ProjectionsRepository $projectionsRepository)
    {
        $this->projectionsRepository = $projectionsRepository;
    }

    /**
     * Get sales projections
     */
    public function getSalesProjections($period, $monthsAhead = 12, $projectId = null, $employeeId = null)
    {
        return $this->projectionsRepository->getSalesProjections($period, $monthsAhead, $projectId, $employeeId);
    }

    /**
     * Get cash flow projections
     */
    public function getCashFlowProjections($period, $monthsAhead = 12, $includePending = true)
    {
        // Check if we have any data in the contracts table
        $hasData = \DB::table('contracts')->count() > 0;
        
        if (!$hasData) {
            // Return mock data when no real data exists
            return $this->getMockCashFlowData($monthsAhead);
        }
        
        return $this->projectionsRepository->getCashFlowProjections($period, $monthsAhead, $includePending);
    }

    /**
     * Get inventory projections
     */
    public function getInventoryProjections($projectId = null, $monthsAhead = 12, $includeReserved = true)
    {
        return $this->projectionsRepository->getInventoryProjections($projectId, $monthsAhead, $includeReserved);
    }

    /**
     * Get market analysis projections
     */
    public function getMarketAnalysis($region = null, $propertyType = null, $monthsAhead = 12)
    {
        return $this->projectionsRepository->getMarketAnalysis($region, $propertyType, $monthsAhead);
    }

    /**
     * Get mock cash flow data when no real data exists
     */
    private function getMockCashFlowData($monthsAhead)
    {
        $projections = [];
        $currentDate = Carbon::now();
        
        for ($i = 0; $i < $monthsAhead; $i++) {
            $date = $currentDate->copy()->addMonths($i);
            $projections[] = [
                'period' => $date->format('Y-m'),
                'period_name' => $date->format('F Y'),
                'projected_income' => 0,
                'projected_expenses' => 0,
                'net_cash_flow' => 0,
                'cumulative_cash_flow' => 0
            ];
        }
        
        return $projections;
    }

    /**
     * Get ROI projections
     */
    public function getROIProjections($projectId = null, $investmentAmount = null, $monthsAhead = 24)
    {
        return $this->projectionsRepository->getROIProjections($projectId, $investmentAmount, $monthsAhead);
    }

    /**
     * Get scenario analysis
     */
    public function getScenarioAnalysis($scenarioType, $projectId = null, $monthsAhead = 12, $variables = [])
    {
        return $this->projectionsRepository->getScenarioAnalysis($scenarioType, $projectId, $monthsAhead, $variables);
    }
}