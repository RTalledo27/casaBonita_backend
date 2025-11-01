<?php

namespace Modules\Reports\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProjectionsRepository
{
    /**
     * Get sales projections
     */
    public function getSalesProjections($period, $monthsAhead = 12, $projectId = null, $employeeId = null)
    {
        // Get historical data for trend analysis
        $historicalData = $this->getHistoricalSalesData($period, $projectId, $employeeId);
        
        // Calculate projections based on historical trends
        $projections = $this->calculateSalesProjections($historicalData, $period, $monthsAhead);
        
        return [
            'historical_data' => $historicalData,
            'projections' => $projections,
            'methodology' => 'Linear trend analysis based on last 12 months',
            'confidence_level' => $this->calculateConfidenceLevel($historicalData)
        ];
    }

    /**
     * Get cash flow projections
     */
    public function getCashFlowProjections($period, $monthsAhead = 12, $includePending = true)
    {
        $projections = [];
        $startDate = Carbon::now()->startOfMonth();
        
        for ($i = 0; $i < $monthsAhead; $i++) {
            $currentDate = $startDate->copy()->addMonths($i);
            
            // Scheduled payments for this period
            $scheduledIncome = $this->getScheduledPayments($currentDate, $period);
            
            // Projected new sales income
            $projectedSales = $this->getProjectedSalesIncome($currentDate, $period);
            
            // Estimated expenses
            $estimatedExpenses = $this->getEstimatedExpenses($currentDate, $period);
            
            $projections[] = [
                'period' => $currentDate->format('Y-m'),
                'scheduled_income' => $scheduledIncome,
                'projected_sales' => $projectedSales,
                'estimated_expenses' => $estimatedExpenses,
                'net_cash_flow' => $scheduledIncome + $projectedSales - $estimatedExpenses
            ];
        }
        
        return $projections;
    }

    /**
     * Get inventory projections
     */
    public function getInventoryProjections($projectId = null, $monthsAhead = 12, $includeReserved = true)
    {
        $query = DB::table('lots as l')
            ->leftJoin('reservations as r', 'l.lot_id', '=', 'r.lot_id')
            ->leftJoin('contracts as c', 'r.reservation_id', '=', 'c.reservation_id')
            ->where('l.status', 'disponible');

        if ($projectId) {
            $query->where('l.manzana_id', $projectId);
        }

        $availableLots = $query->selectRaw('
            p.project_id,
            p.project_name,
            COUNT(*) as available_lots,
            AVG(l.price) as average_price,
            SUM(l.price) as total_inventory_value
        ')
        ->groupBy('p.project_id', 'p.project_name')
        ->get();

        // Calculate sales velocity
        $salesVelocity = $this->calculateSalesVelocity($projectId);
        
        $projections = [];
        foreach ($availableLots as $lot) {
            $monthlyVelocity = $salesVelocity[$lot->project_id] ?? 0;
            $projections[] = [
                'project_id' => $lot->project_id,
                'project_name' => $lot->project_name,
                'current_inventory' => $lot->available_lots,
                'monthly_velocity' => $monthlyVelocity,
                'months_to_sellout' => $monthlyVelocity > 0 ? 
                    round($lot->available_lots / $monthlyVelocity, 1) : null,
                'projected_revenue' => $lot->total_inventory_value
            ];
        }
        
        return $projections;
    }

    /**
     * Get market analysis projections
     */
    public function getMarketAnalysis($region = null, $propertyType = null, $monthsAhead = 12)
    {
        // This would typically integrate with external market data
        // For now, we'll use internal sales data to estimate market trends
        
        $query = DB::table('contracts as c')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->join('lots as l', 'c.lot_id', '=', 'l.lot_id')
            ->where('c.created_at', '>=', Carbon::now()->subYear())
            ->where('c.status', 'vigente');

        if ($region) {
            $query->where('l.manzana_id', $region);
        }

        if ($propertyType) {
            $query->where('l.area_m2', '>', $propertyType);
        }

        $marketData = $query->selectRaw('
            DATE_FORMAT(c.created_at, "%Y-%m") as month,
            COUNT(*) as sales_volume,
            AVG(c.total_price) as average_price,
            MIN(c.total_price) as min_price,
            MAX(c.total_price) as max_price
        ')
        ->groupBy('month')
        ->orderBy('month')
        ->get();

        // Calculate price trends and projections
        $priceProjections = $this->calculatePriceProjections($marketData, $monthsAhead);
        
        return [
            'historical_data' => $marketData,
            'price_projections' => $priceProjections,
            'market_indicators' => $this->getMarketIndicators($region, $propertyType)
        ];
    }

    /**
     * Get ROI projections
     */
    public function getROIProjections($projectId = null, $investmentAmount = null, $monthsAhead = 24)
    {
        $query = DB::table('lots as l')
            ->leftJoin('reservations as r', 'l.lot_id', '=', 'r.lot_id')
            ->leftJoin('contracts as c', 'r.reservation_id', '=', 'c.reservation_id');

        if ($projectId) {
            $query->where('l.manzana_id', $projectId);
        }

        $projectData = $query->selectRaw('
            l.manzana_id,
            COUNT(l.lot_id) as total_lots,
            COUNT(CASE WHEN c.status = "vigente" THEN c.contract_id END) as sold_lots,
            SUM(CASE WHEN c.status = "vigente" THEN c.total_price ELSE 0 END) as revenue_to_date,
            AVG(CASE WHEN c.status = "vigente" THEN c.total_price END) as average_sale_price
        ')
        ->groupBy('l.manzana_id')
        ->get();

        $projections = [];
        foreach ($projectData as $project) {
            $remainingLots = $project->total_lots - $project->sold_lots;
            $projectedRevenue = $remainingLots * $project->average_sale_price;
            $totalProjectedRevenue = $project->revenue_to_date + $projectedRevenue;
            $investment = $investmentAmount ?? $project->total_investment;
            
            $projections[] = [
                'project_id' => $project->project_id,
                'project_name' => $project->project_name,
                'initial_investment' => $investment,
                'revenue_to_date' => $project->revenue_to_date,
                'projected_total_revenue' => $totalProjectedRevenue,
                'projected_roi' => $investment > 0 ? 
                    round((($totalProjectedRevenue - $investment) / $investment) * 100, 2) : 0,
                'payback_period_months' => $this->calculatePaybackPeriod($project, $investment)
            ];
        }
        
        return $projections;
    }

    /**
     * Get scenario analysis
     */
    public function getScenarioAnalysis($scenarioType, $projectId = null, $monthsAhead = 12, $variables = [])
    {
        $baseProjections = $this->getSalesProjections('monthly', $monthsAhead, $projectId);
        
        // Apply scenario multipliers
        $multipliers = $this->getScenarioMultipliers($scenarioType);
        
        $scenarios = [];
        foreach ($baseProjections['projections'] as $projection) {
            $scenarios[] = [
                'period' => $projection['period'],
                'base_projection' => $projection['projected_sales'],
                'scenario_projection' => $projection['projected_sales'] * $multipliers['sales'],
                'scenario_type' => $scenarioType,
                'confidence_level' => $multipliers['confidence']
            ];
        }
        
        return [
            'scenario_type' => $scenarioType,
            'projections' => $scenarios,
            'assumptions' => $this->getScenarioAssumptions($scenarioType),
            'variables_used' => $variables
        ];
    }

    /**
     * Get historical sales data
     */
    private function getHistoricalSalesData($period, $projectId = null, $employeeId = null)
    {
        $dateFormat = $this->getDateFormat($period);
        $startDate = Carbon::now()->subYear();
        
        $query = DB::table('sales as s')
            ->where('s.sale_date', '>=', $startDate);

        if ($projectId) {
            $query->where('s.project_id', $projectId);
        }

        if ($employeeId) {
            $query->where('s.employee_id', $employeeId);
        }

        return $query->selectRaw("
            {$dateFormat} as period,
            COUNT(*) as sales_count,
            SUM(c.total_price) as revenue
        ")
        ->groupBy('period')
        ->orderBy('period')
        ->get();
    }

    /**
     * Calculate sales projections based on historical data
     */
    private function calculateSalesProjections($historicalData, $period, $monthsAhead)
    {
        if ($historicalData->isEmpty()) {
            return [];
        }

        // Simple linear trend calculation
        $salesTrend = $this->calculateTrend($historicalData->pluck('sales_count')->toArray());
        $revenueTrend = $this->calculateTrend($historicalData->pluck('revenue')->toArray());
        
        $projections = [];
        $lastSales = $historicalData->last()->sales_count;
        $lastRevenue = $historicalData->last()->revenue;
        
        for ($i = 1; $i <= $monthsAhead; $i++) {
            $projectedSales = max(0, $lastSales + ($salesTrend * $i));
            $projectedRevenue = max(0, $lastRevenue + ($revenueTrend * $i));
            
            $projections[] = [
                'period' => Carbon::now()->addMonths($i)->format('Y-m'),
                'projected_sales' => round($projectedSales),
                'projected_revenue' => round($projectedRevenue, 2)
            ];
        }
        
        return $projections;
    }

    /**
     * Calculate simple linear trend
     */
    private function calculateTrend($data)
    {
        $n = count($data);
        if ($n < 2) return 0;
        
        $sumX = array_sum(range(1, $n));
        $sumY = array_sum($data);
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $y = $data[$i];
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        
        return ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    }

    /**
     * Get scenario multipliers
     */
    private function getScenarioMultipliers($scenarioType)
    {
        switch ($scenarioType) {
            case 'optimistic':
                return ['sales' => 1.2, 'confidence' => 70];
            case 'pessimistic':
                return ['sales' => 0.8, 'confidence' => 70];
            case 'realistic':
            default:
                return ['sales' => 1.0, 'confidence' => 85];
        }
    }

    /**
     * Get date format based on period
     */
    private function getDateFormat($period)
    {
        switch ($period) {
            case 'monthly':
                return "DATE_FORMAT(s.sale_date, '%Y-%m')";
            case 'quarterly':
                return "CONCAT(YEAR(s.sale_date), '-Q', QUARTER(s.sale_date))";
            case 'yearly':
                return "YEAR(s.sale_date)";
            default:
                return "DATE_FORMAT(s.sale_date, '%Y-%m')";
        }
    }

    // Additional helper methods would be implemented here...
    private function calculateConfidenceLevel($data) { return 85; }
    private function getScheduledPayments($date, $period) { return 0; }
    private function getProjectedSalesIncome($date, $period) { return 0; }
    private function getEstimatedExpenses($date, $period) { return 0; }
    private function calculateSalesVelocity($projectId) { return []; }
    private function calculatePriceProjections($data, $months) { return []; }
    private function getMarketIndicators($region, $type) { return []; }
    private function calculatePaybackPeriod($project, $investment) { return 24; }
    private function getScenarioAssumptions($type) { return []; }
}