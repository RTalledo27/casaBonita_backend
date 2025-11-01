<?php

namespace Modules\Reports\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentsRepository
{
    /**
     * Get payments summary
     */
    public function getPaymentsSummary($dateFrom, $dateTo, $status = null)
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->leftJoin('clients as cl', 'r.client_id', '=', 'cl.client_id')
            ->whereBetween('ps.due_date', [$dateFrom, $dateTo]);

        if ($status) {
            $query->where('ps.status', $status);
        }

        return $query->selectRaw('
            COUNT(*) as total_payments,
            SUM(ps.amount) as total_amount,
            SUM(CASE WHEN ps.status = "paid" THEN ps.amount ELSE 0 END) as paid_amount,
            SUM(CASE WHEN ps.status = "pending" THEN ps.amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN ps.status = "overdue" THEN ps.amount ELSE 0 END) as overdue_amount,
            COUNT(DISTINCT r.client_id) as unique_clients
        ')->first();
    }

    /**
     * Get status breakdown
     */
    public function getStatusBreakdown($dateFrom, $dateTo)
    {
        return DB::table('payment_schedules as ps')
            ->whereBetween('ps.due_date', [$dateFrom, $dateTo])
            ->selectRaw('
                ps.status,
                COUNT(*) as count,
                SUM(ps.amount) as total_amount
            ')
            ->groupBy('ps.status')
            ->get();
    }

    /**
     * Get payments by status
     */
    public function getByStatus($status, $dateFrom = null, $dateTo = null, $clientId = null, $page = 1, $perPage = 20)
    {
        $query = DB::table('payment_schedules as ps')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->join('clients as cl', 'r.client_id', '=', 'cl.client_id')
            ->leftJoin('lots as l', 'c.lot_id', '=', 'l.lot_id')
            ->where('ps.status', $status);

        if ($dateFrom && $dateTo) {
            $query->whereBetween('ps.due_date', [$dateFrom, $dateTo]);
        }

        if ($clientId) {
            $query->where('r.client_id', $clientId);
        }

        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $payments = $query->select([
                'ps.schedule_id as payment_schedule_id',
                'ps.amount',
                'ps.due_date',
                'ps.paid_date',
                'ps.status',
                'cl.name as client_name',
                'cl.email as client_email',
                'l.lot_number',
                'p.project_name',
                'c.total_price as sale_amount'
            ])
            ->orderBy('ps.due_date', 'asc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return [
            'data' => $payments,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }

    /**
     * Get overdue payments
     */
    public function getOverdue($daysOverdue = null, $clientId = null, $page = 1, $perPage = 20)
    {
        $query = DB::table('payment_schedules as ps')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->join('clients as cl', 'r.client_id', '=', 'cl.client_id')
            ->leftJoin('lots as l', 'c.lot_id', '=', 'l.lot_id')
            ->where('ps.status', 'overdue')
            ->where('ps.due_date', '<', Carbon::now());

        if ($daysOverdue) {
            $query->where('ps.due_date', '<', Carbon::now()->subDays($daysOverdue));
        }

        if ($clientId) {
            $query->where('r.client_id', $clientId);
        }

        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $payments = $query->selectRaw('
                ps.schedule_id as payment_schedule_id,
                ps.amount,
                ps.due_date,
                ps.status,
                cl.name as client_name,
                cl.email as client_email,
                cl.phone as client_phone,
                l.lot_number,
                p.project_name,
                c.total_price as sale_amount,
                DATEDIFF(NOW(), ps.due_date) as days_overdue
            ')
            ->orderBy('days_overdue', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return [
            'data' => $payments,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }

    /**
     * Get payment trends
     */
    public function getPaymentTrends($period, $dateFrom, $dateTo)
    {
        $dateFormat = $this->getDateFormat($period);
        
        return DB::table('payment_schedules as ps')
            ->whereBetween('ps.paid_date', [$dateFrom, $dateTo])
            ->where('ps.status', 'paid')
            ->selectRaw("
                {$dateFormat} as period,
                COUNT(*) as payments_count,
                SUM(ps.amount) as total_amount,
                AVG(ps.amount) as average_payment
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get();
    }

    /**
     * Get collection efficiency
     */
    public function getCollectionEfficiency($dateFrom, $dateTo, $employeeId = null)
    {
        $query = DB::table('payment_schedules as ps')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->whereBetween('ps.due_date', [$dateFrom, $dateTo]);

        if ($employeeId) {
            $query->where('c.advisor_id', $employeeId);
        }

        $result = $query->selectRaw('
            COUNT(*) as total_scheduled,
            COUNT(CASE WHEN ps.status = "paid" THEN 1 END) as paid_count,
            COUNT(CASE WHEN ps.status = "paid" AND ps.paid_date <= ps.due_date THEN 1 END) as on_time_count,
            SUM(ps.amount) as total_amount,
            SUM(CASE WHEN ps.status = "paid" THEN ps.amount ELSE 0 END) as collected_amount
        ')->first();

        return [
            'total_scheduled' => $result->total_scheduled,
            'paid_count' => $result->paid_count,
            'on_time_count' => $result->on_time_count,
            'collection_rate' => $result->total_scheduled > 0 ? 
                round(($result->paid_count / $result->total_scheduled) * 100, 2) : 0,
            'on_time_rate' => $result->total_scheduled > 0 ? 
                round(($result->on_time_count / $result->total_scheduled) * 100, 2) : 0,
            'amount_efficiency' => $result->total_amount > 0 ? 
                round(($result->collected_amount / $result->total_amount) * 100, 2) : 0
        ];
    }

    /**
     * Get upcoming payments
     */
    public function getUpcoming($daysAhead = 30, $clientId = null, $page = 1, $perPage = 20)
    {
        $query = DB::table('payment_schedules as ps')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->join('clients as cl', 'r.client_id', '=', 'cl.client_id')
            ->leftJoin('lots as l', 'c.lot_id', '=', 'l.lot_id')
            ->where('ps.status', 'pending')
            ->whereBetween('ps.due_date', [Carbon::now(), Carbon::now()->addDays($daysAhead)]);

        if ($clientId) {
            $query->where('r.client_id', $clientId);
        }

        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $payments = $query->selectRaw('
                ps.payment_schedule_id,
                ps.amount,
                ps.due_date,
                ps.status,
                c.name as client_name,
                c.email as client_email,
                c.phone as client_phone,
                l.lot_number,
                p.project_name,
                c.total_price as sale_amount,
                DATEDIFF(ps.due_date, NOW()) as days_until_due
            ')
            ->orderBy('ps.due_date', 'asc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return [
            'data' => $payments,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }

    /**
     * Get overdue summary
     */
    public function getOverdueSummary()
    {
        return DB::table('payment_schedules as ps')
            ->where('ps.status', 'overdue')
            ->where('ps.due_date', '<', Carbon::now())
            ->selectRaw('
                COUNT(*) as total_overdue,
                SUM(ps.amount) as total_overdue_amount,
                COUNT(CASE WHEN ps.due_date < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as overdue_30_days,
                COUNT(CASE WHEN ps.due_date < DATE_SUB(NOW(), INTERVAL 60 DAY) THEN 1 END) as overdue_60_days,
                COUNT(CASE WHEN ps.due_date < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 END) as overdue_90_days
            ')->first();
    }

    /**
     * Get date format based on period
     */
    private function getDateFormat($period)
    {
        switch ($period) {
            case 'daily':
                return "DATE_FORMAT(ps.paid_date, '%Y-%m-%d')";
            case 'weekly':
                return "DATE_FORMAT(ps.paid_date, '%Y-%u')";
            case 'monthly':
                return "DATE_FORMAT(ps.paid_date, '%Y-%m')";
            case 'quarterly':
                return "CONCAT(YEAR(ps.paid_date), '-Q', QUARTER(ps.paid_date))";
            default:
                return "DATE_FORMAT(ps.paid_date, '%Y-%m-%d')";
        }
    }
}