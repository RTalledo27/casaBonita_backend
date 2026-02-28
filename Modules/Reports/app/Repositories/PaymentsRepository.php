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
            SUM(CASE WHEN ps.status = "pagado" THEN ps.amount ELSE 0 END) as paid_amount,
            SUM(CASE WHEN ps.status = "pendiente" AND ps.due_date >= CURDATE() THEN ps.amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN ps.status = "vencido" OR (ps.status = "pendiente" AND ps.due_date < CURDATE()) THEN ps.amount ELSE 0 END) as overdue_amount,
            COUNT(DISTINCT c.client_id) as unique_clients
        ')->first();
    }

    /**
     * Get status breakdown
     */
    public function getStatusBreakdown($dateFrom, $dateTo)
    {
        $rawStats = DB::table('payment_schedules as ps')
            ->whereBetween('ps.due_date', [$dateFrom, $dateTo])
            ->selectRaw('
                ps.status,
                ps.due_date < CURDATE() as is_overdue,
                COUNT(*) as count,
                SUM(ps.amount) as total_amount
            ')
            ->groupBy('ps.status', DB::raw('ps.due_date < CURDATE()'))
            ->get();
            
        return $rawStats->map(function ($item) {
            $status = $item->status;
            if ($status === 'pendiente' && $item->is_overdue) {
                $status = 'vencido';
            }
            return (object) [
                'status' => $status,
                'count' => $item->count,
                'total_amount' => $item->total_amount
            ];
        })->groupBy('status')->map(function ($group, $status) {
            return (object) [
                'status' => $status,
                'count' => $group->sum('count'),
                'total_amount' => $group->sum('total_amount'),
            ];
        })->values();
    }

    /**
     * Get payments by status
     */
    public function getByStatus($status = null, $dateFrom = null, $dateTo = null, $clientId = null, $page = 1, $perPage = 20, $options = [])
    {
        $query = DB::table('payment_schedules as ps')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->leftJoin('clients as cl', 'c.client_id', '=', 'cl.client_id')
            ->leftJoin('lots as l', 'c.lot_id', '=', 'l.lot_id');

        if ($status) {
            if ($status === "vencido") {
                $query->where(function($q) {
                    $q->where('ps.status', 'vencido')
                      ->orWhere(function($sub) {
                          $sub->where('ps.status', 'pendiente')
                              ->where('ps.due_date', '<', \Carbon\Carbon::now()->startOfDay());
                      });
                });
            } elseif ($status === "pendiente") {
                $query->where('ps.status', 'pendiente')
                      ->where('ps.due_date', '>=', \Carbon\Carbon::now()->startOfDay());
            } else {
                $query->where('ps.status', $status);
            }
        }

        if ($dateFrom && $dateTo) {
            $query->whereBetween('ps.due_date', [$dateFrom, $dateTo]);
        }

        if ($clientId) {
            $query->where('c.client_id', $clientId);
        }

        // Apply text search term if provided
        if (!empty($options['searchTerm'])) {
            $term = '%' . $options['searchTerm'] . '%';
            $query->where(function($q) use ($term) {
                $q->where('cl.first_name', 'like', $term)
                  ->orWhere('cl.last_name', 'like', $term)
                  ->orWhere('cl.email', 'like', $term)
                  ->orWhere('l.num_lot', 'like', $term)
                  ->orWhere(DB::raw("CONCAT(cl.first_name, ' ', cl.last_name)"), 'like', $term);
            });
        }

        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        // Apply sorting if provided, else default to due_date
        $sortField = !empty($options['sortField']) ? $options['sortField'] : 'due_date';
        $sortDirection = !empty($options['sortDirection']) ? $options['sortDirection'] : 'asc';
        
        $dbSortField = 'ps.due_date';
        if ($sortField === 'clientName') $dbSortField = 'cl.first_name';
        if ($sortField === 'lotNumber') $dbSortField = 'l.num_lot';
        if ($sortField === 'amount') $dbSortField = 'ps.amount';
        if ($sortField === 'status') $dbSortField = 'ps.status';

        $payments = $query->selectRaw("
                ps.schedule_id,
                ps.amount,
                ps.due_date,
                CASE WHEN ps.status = 'pendiente' AND ps.due_date < CURDATE() THEN 'vencido' ELSE ps.status END as status,
                CONCAT(cl.first_name, ' ', cl.last_name) as client_name,
                cl.email as client_email,
                l.num_lot as lot_number,
                c.total_price as sale_amount
            ")
            ->orderBy($dbSortField, $sortDirection)
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
            ->leftJoin('clients as cl', 'c.client_id', '=', 'cl.client_id')
            ->leftJoin('lots as l', 'c.lot_id', '=', 'l.lot_id')
            ->where(function($q) {
                $q->where('ps.status', 'vencido')
                  ->orWhere(function($subq) {
                      $subq->where('ps.status', 'pendiente')
                           ->where('ps.due_date', '<', \Carbon\Carbon::now()->startOfDay());
                  });
            });

        if ($daysOverdue) {
            $query->where('ps.due_date', '<', \Carbon\Carbon::now()->subDays($daysOverdue));
        }

        if ($clientId) {
            $query->where('c.client_id', $clientId);
        }

        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $payments = $query->selectRaw("
                ps.schedule_id,
                ps.amount,
                ps.due_date,
                'vencido' as status,
                CONCAT(cl.first_name, ' ', cl.last_name) as client_name,
                cl.email as client_email,
                cl.primary_phone as client_phone,
                l.num_lot as lot_number,
                c.total_price as sale_amount,
                DATEDIFF(NOW(), ps.due_date) as days_overdue
            ")
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
            ->whereBetween('ps.due_date', [$dateFrom, $dateTo])
            ->where('ps.status', 'pagado')
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
            COUNT(CASE WHEN ps.status = "pagado" THEN 1 END) as paid_count,
            COUNT(CASE WHEN ps.status = "pagado" AND ps.due_date >= NOW() THEN 1 END) as on_time_count,
            SUM(ps.amount) as total_amount,
            SUM(CASE WHEN ps.status = "pagado" THEN ps.amount ELSE 0 END) as collected_amount
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
            ->leftJoin('clients as cl', 'c.client_id', '=', 'cl.client_id')
            ->leftJoin('lots as l', 'c.lot_id', '=', 'l.lot_id')
            ->where('ps.status', 'pendiente')
            ->whereBetween('ps.due_date', [\Carbon\Carbon::now()->startOfDay(), \Carbon\Carbon::now()->addDays($daysAhead)]);

        if ($clientId) {
            $query->where('c.client_id', $clientId);
        }

        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $payments = $query->selectRaw("
                ps.schedule_id,
                ps.amount,
                ps.due_date,
                ps.status,
                CONCAT(cl.first_name, ' ', cl.last_name) as client_name,
                cl.email as client_email,
                cl.primary_phone as client_phone,
                l.num_lot as lot_number,
                c.total_price as sale_amount,
                DATEDIFF(ps.due_date, NOW()) as days_until_due
            ")
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
            ->where(function($q) {
                $q->where('ps.status', 'vencido')
                  ->orWhere(function($subq) {
                      $subq->where('ps.status', 'pendiente')
                           ->where('ps.due_date', '<', \Carbon\Carbon::now()->startOfDay());
                  });
            })
            ->selectRaw('
                COUNT(*) as total_overdue,
                SUM(ps.amount) as total_overdue_amount,
                COUNT(CASE WHEN ps.due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as overdue_30_days,
                COUNT(CASE WHEN ps.due_date < DATE_SUB(CURDATE(), INTERVAL 60 DAY) THEN 1 END) as overdue_60_days,
                COUNT(CASE WHEN ps.due_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 END) as overdue_90_days
            ')->first();
    }

    /**
     * Get date format based on period
     */
    private function getDateFormat($period)
    {
        switch ($period) {
            case 'daily':
                return "DATE_FORMAT(ps.due_date, '%Y-%m-%d')";
            case 'weekly':
                return "DATE_FORMAT(ps.due_date, '%Y-%u')";
            case 'monthly':
                return "DATE_FORMAT(ps.due_date, '%Y-%m')";
            case 'quarterly':
                return "CONCAT(YEAR(ps.due_date), '-Q', QUARTER(ps.due_date))";
            default:
                return "DATE_FORMAT(ps.due_date, '%Y-%m-%d')";
        }
    }
}