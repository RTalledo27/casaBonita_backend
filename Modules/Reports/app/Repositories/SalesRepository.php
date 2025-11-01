<?php

namespace Modules\Reports\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesRepository
{
    /**
     * Get all sales with detailed information
     */
    public function getAllSales($dateFrom, $dateTo, $employeeId = null, $projectId = null, $limit = 100, $offset = 0)
    {
        $query = DB::table('contracts as c')
            ->leftJoin('employees as e', 'c.advisor_id', '=', 'e.employee_id')
            ->leftJoin('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->leftJoin('lots as l', 'r.lot_id', '=', 'l.lot_id')
            ->leftJoin('manzanas as m', 'l.manzana_id', '=', 'm.manzana_id')
            ->whereBetween('c.sign_date', [$dateFrom, $dateTo])
            ->where('c.status', 'vigente');

        if ($employeeId) {
            $query->where('c.advisor_id', $employeeId);
        }

        if ($projectId) {
            $query->where('c.project_id', $projectId);
        }

        return $query->selectRaw('
            c.contract_id,
            c.contract_number,
            c.sign_date,
            c.total_price,
            c.down_payment,
            c.financing_amount,
            c.term_months,
            c.monthly_payment,
            c.interest_rate,
            c.status,
            e.employee_id,
            CONCAT(u.first_name, " ", u.last_name) as advisor_name,
            c.client_id,
            l.lot_id,
            l.num_lot as lot_number,
            m.name as manzana_name,
            l.area_m2 as lot_area,
            l.total_price as lot_price,
            c.created_at,
            c.updated_at
        ')
        ->orderBy('c.sign_date', 'desc')
        ->limit($limit)
        ->offset($offset)
        ->get();
    }

    /**
     * Get sales summary
     */
    public function getSalesSummary($dateFrom, $dateTo, $employeeId = null, $projectId = null)
    {
        $query = DB::table('contracts as c')
            ->whereBetween('c.sign_date', [$dateFrom, $dateTo])
            ->where('c.status', 'vigente');

        if ($employeeId) {
            $query->where('c.advisor_id', $employeeId);
        }

        if ($projectId) {
            $query->where('c.project_id', $projectId);
        }

        return $query->selectRaw('
            COUNT(*) as total_sales,
            SUM(c.total_price) as total_revenue,
            AVG(c.total_price) as average_sale,
            COUNT(DISTINCT c.client_id) as unique_clients,
            COUNT(DISTINCT c.advisor_id) as active_employees
        ')->first();
    }

    /**
     * Get sales trends
     */
    public function getSalesTrends($dateFrom, $dateTo, $period = 'monthly', $employeeId = null)
    {
        $dateFormat = $period === 'daily' ? '%Y-%m-%d' : '%Y-%m';
        
        $query = DB::table('contracts as c')
            ->whereBetween('c.sign_date', [$dateFrom, $dateTo])
            ->where('c.status', 'vigente');

        if ($employeeId) {
            $query->where('c.advisor_id', $employeeId);
        }

        return $query->selectRaw("
            DATE_FORMAT(c.sign_date, '{$dateFormat}') as period,
            COUNT(*) as sales_count,
            SUM(c.total_price) as total_revenue,
            AVG(c.total_price) as average_sale
        ")
        ->groupBy('period')
        ->orderBy('period')
        ->get();
    }

    /**
     * Get sales by period
     */
    public function getSalesByPeriod($dateFrom, $dateTo, $period = 'monthly', $employeeId = null)
    {
        $dateFormat = $period === 'daily' ? '%Y-%m-%d' : '%Y-%m';
        
        $query = DB::table('contracts as c')
            ->whereBetween('c.sign_date', [$dateFrom, $dateTo])
            ->where('c.status', 'vigente');

        if ($employeeId) {
            $query->where('c.advisor_id', $employeeId);
        }

        return $query->selectRaw("
            DATE_FORMAT(c.sign_date, '{$dateFormat}') as period,
            COUNT(*) as sales_count,
            SUM(c.total_price) as total_revenue,
            AVG(c.total_price) as average_sale
        ")
        ->groupBy('period')
        ->orderBy('period')
        ->get();
    }

    /**
     * Get sales performance by employee
     */
    public function getSalesPerformance($dateFrom, $dateTo, $department = null)
    {
        $query = DB::table('contracts as c')
            ->leftJoin('employees as e', 'c.advisor_id', '=', 'e.employee_id')
            ->leftJoin('users as u', 'e.user_id', '=', 'u.user_id')
            ->whereBetween('c.sign_date', [$dateFrom, $dateTo])
            ->where('c.status', 'vigente');

        if ($department) {
            // Assuming department is stored in employees table
            $query->where('e.employee_type', $department);
        }

        return $query->selectRaw('
            e.employee_id,
            CONCAT(u.first_name, " ", u.last_name) as employee_name,
            e.employee_type as department,
            COUNT(*) as sales_count,
            SUM(c.total_price) as total_revenue,
            AVG(c.total_price) as average_sale,
            COUNT(DISTINCT c.client_id) as unique_clients
        ')
        ->groupBy('e.employee_id', 'u.first_name', 'u.last_name', 'e.employee_type')
        ->orderBy('total_revenue', 'desc')
        ->get();
    }

    /**
     * Get conversion funnel
     */
    public function getConversionFunnel($dateFrom, $dateTo, $employeeId = null)
    {
        // This would need to be adapted based on your actual lead/prospect tracking system
        $query = DB::table('leads as l')
            ->leftJoin('sales as s', 'l.lead_id', '=', 's.lead_id')
            ->whereBetween('l.created_at', [$dateFrom, $dateTo]);

        if ($employeeId) {
            $query->where('l.assigned_to', $employeeId);
        }

        return $query->selectRaw('
            COUNT(*) as total_leads,
            COUNT(CASE WHEN l.status = "contacted" THEN 1 END) as contacted_leads,
            COUNT(CASE WHEN l.status = "qualified" THEN 1 END) as qualified_leads,
            COUNT(CASE WHEN l.status = "proposal" THEN 1 END) as proposal_leads,
            COUNT(CASE WHEN s.sale_id IS NOT NULL THEN 1 END) as converted_sales
        ')->first();
    }

    /**
     * Get top selling products/lots
     */
    public function getTopProducts($dateFrom, $dateTo, $limit = 10)
    {
        return DB::table('contracts as c')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->join('lots as l', 'r.lot_id', '=', 'l.lot_id')
            ->whereBetween('c.sign_date', [$dateFrom, $dateTo])
            ->where('c.status', 'vigente')
            ->selectRaw('
                l.lot_id,
                l.num_lot,
                l.manzana_id,
                COUNT(*) as sales_count,
                SUM(c.total_price) as total_revenue,
                AVG(c.total_price) as average_price
            ')
            ->groupBy('l.lot_id', 'l.num_lot', 'l.manzana_id')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get top performers
     */
    public function getTopPerformers($dateFrom, $dateTo, $projectId = null, $limit = 5)
    {
        $query = DB::table('contracts as c')
            ->leftJoin('employees as e', 'c.advisor_id', '=', 'e.employee_id')
            ->leftJoin('users as u', 'e.user_id', '=', 'u.user_id')
            ->whereBetween('c.sign_date', [$dateFrom, $dateTo])
            ->where('c.status', 'vigente');

        if ($projectId) {
            $query->where('c.project_id', $projectId);
        }

        return $query->selectRaw('
            e.employee_id,
            CONCAT(u.first_name, " ", u.last_name) as employee_name,
            COUNT(*) as sales_count,
            SUM(c.total_price) as total_revenue,
            MAX(c.sign_date) as latest_sale_date,
            MIN(c.sign_date) as first_sale_date
        ')
        ->groupBy('e.employee_id', 'u.first_name', 'u.last_name')
        ->orderBy('total_revenue', 'desc')
        ->limit($limit)
        ->get();
    }

    /**
     * Get conversion rates
     */
    public function getConversionRates($dateFrom, $dateTo, $employeeId = null, $projectId = null)
    {
        // Since we don't have a leads table, we'll calculate conversion based on reservations to contracts
        $query = DB::table('reservations as r')
            ->leftJoin('contracts as c', 'r.reservation_id', '=', 'c.reservation_id')
            ->leftJoin('lots as l', 'r.lot_id', '=', 'l.lot_id')
            ->whereBetween('r.reservation_date', [$dateFrom, $dateTo]);

        if ($employeeId) {
            $query->where('r.advisor_id', $employeeId);
        }

        if ($projectId) {
            $query->where('l.manzana_id', $projectId);
        }

        $result = $query->selectRaw('
            COUNT(*) as total_reservations,
            COUNT(CASE WHEN c.contract_id IS NOT NULL AND c.status = "vigente" THEN 1 END) as converted_contracts
        ')->first();

        $totalReservations = $result->total_reservations ?? 0;
        $convertedContracts = $result->converted_contracts ?? 0;

        return [
            'leads_to_prospects' => $totalReservations,
            'prospects_to_sales' => $convertedContracts,
            'overall_conversion' => $totalReservations > 0 ? 
                round(($convertedContracts / $totalReservations) * 100, 2) : 0
        ];
    }

    /**
     * Get date format based on period
     */
    private function getDateFormat($period)
    {
        switch ($period) {
            case 'daily':
                return "DATE_FORMAT(s.sale_date, '%Y-%m-%d')";
            case 'weekly':
                return "DATE_FORMAT(s.sale_date, '%Y-%u')";
            case 'monthly':
                return "DATE_FORMAT(s.sale_date, '%Y-%m')";
            case 'quarterly':
                return "CONCAT(YEAR(s.sale_date), '-Q', QUARTER(s.sale_date))";
            case 'yearly':
                return "YEAR(s.sale_date)";
            default:
                return "DATE_FORMAT(s.sale_date, '%Y-%m-%d')";
        }
    }
}