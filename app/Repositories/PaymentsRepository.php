<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentsRepository
{
    /**
     * Get payment schedules with filters and pagination
     */
    public function getPaymentSchedules(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->leftJoin('users as advisor', 'c.advisor_id', '=', 'advisor.id')
            ->leftJoin('offices as o', 'c.office_id', '=', 'o.id')
            ->leftJoin('lots as l', 'c.lot_id', '=', 'l.id')
            ->leftJoin('projects as p', 'l.project_id', '=', 'p.id')
            ->select([
                'ps.id as payment_id',
                'ps.payment_number',
                'ps.due_date',
                'ps.amount',
                'ps.paid_amount',
                'ps.status',
                'ps.payment_date',
                'ps.payment_method',
                'c.id as contract_id',
                'c.contract_number',
                'c.client_name',
                'advisor.name as advisor_name',
                'o.name as office_name',
                'p.name as project_name',
                'l.lot_number'
            ]);

        // Apply filters
        if (!empty($filters['start_date'])) {
            $query->where('ps.due_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('ps.due_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('ps.status', $filters['status']);
            } else {
                $query->where('ps.status', $filters['status']);
            }
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

        if (!empty($filters['client_name'])) {
            $query->where('c.client_name', 'LIKE', '%' . $filters['client_name'] . '%');
        }

        if (!empty($filters['contract_number'])) {
            $query->where('c.contract_number', 'LIKE', '%' . $filters['contract_number'] . '%');
        }

        // Get total count for pagination
        $totalCount = $query->count();

        // Apply pagination
        $offset = ($page - 1) * $perPage;
        $results = $query->orderBy('ps.due_date', 'asc')
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
     * Get overdue payments
     */
    public function getOverduePayments(array $filters = []): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->leftJoin('users as advisor', 'c.advisor_id', '=', 'advisor.id')
            ->leftJoin('offices as o', 'c.office_id', '=', 'o.id')
            ->where('ps.status', 'pending')
            ->where('ps.due_date', '<', Carbon::now()->format('Y-m-d'))
            ->select([
                'ps.id as payment_id',
                'ps.payment_number',
                'ps.due_date',
                'ps.amount',
                'ps.paid_amount',
                'c.id as contract_id',
                'c.contract_number',
                'c.client_name',
                'advisor.name as advisor_name',
                'o.name as office_name',
                DB::raw('DATEDIFF(CURDATE(), ps.due_date) as days_overdue')
            ]);

        // Apply additional filters
        if (!empty($filters['advisor_id'])) {
            $query->where('c.advisor_id', $filters['advisor_id']);
        }

        if (!empty($filters['office_id'])) {
            $query->where('c.office_id', $filters['office_id']);
        }

        if (!empty($filters['days_overdue_min'])) {
            $query->havingRaw('days_overdue >= ?', [$filters['days_overdue_min']]);
        }

        if (!empty($filters['days_overdue_max'])) {
            $query->havingRaw('days_overdue <= ?', [$filters['days_overdue_max']]);
        }

        return $query->orderBy('ps.due_date', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Get payment calendar data
     */
    public function getPaymentCalendar(string $startDate, string $endDate, ?int $officeId = null): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->where('ps.due_date', '>=', $startDate)
            ->where('ps.due_date', '<=', $endDate)
            ->select([
                'ps.due_date',
                'ps.status',
                DB::raw('COUNT(*) as payment_count'),
                DB::raw('SUM(ps.amount) as total_amount'),
                DB::raw('SUM(ps.paid_amount) as total_paid')
            ])
            ->groupBy('ps.due_date', 'ps.status');

        if ($officeId) {
            $query->where('c.office_id', $officeId);
        }

        return $query->orderBy('ps.due_date')
            ->get()
            ->toArray();
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStatistics(array $filters = []): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id');

        $this->applyFilters($query, $filters);

        $stats = $query->selectRaw('
            COUNT(*) as total_payments,
            SUM(ps.amount) as total_scheduled,
            SUM(ps.paid_amount) as total_collected,
            SUM(CASE WHEN ps.status = "paid" THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN ps.status = "pending" THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN ps.status = "overdue" THEN 1 ELSE 0 END) as overdue_count,
            SUM(CASE WHEN ps.status = "pending" AND ps.due_date < CURDATE() THEN 1 ELSE 0 END) as actual_overdue_count
        ')->first();

        // Calculate collection rate
        $collectionRate = $stats->total_scheduled > 0 
            ? ($stats->total_collected / $stats->total_scheduled) * 100 
            : 0;

        // Get aging analysis
        $agingAnalysis = $this->getPaymentAging($filters);

        // Get payment method distribution
        $paymentMethods = $this->getPaymentMethodDistribution($filters);

        return [
            'summary' => [
                'total_payments' => (int) $stats->total_payments,
                'total_scheduled' => (float) $stats->total_scheduled,
                'total_collected' => (float) $stats->total_collected,
                'collection_rate' => round($collectionRate, 2),
                'paid_count' => (int) $stats->paid_count,
                'pending_count' => (int) $stats->pending_count,
                'overdue_count' => (int) $stats->actual_overdue_count
            ],
            'aging_analysis' => $agingAnalysis,
            'payment_methods' => $paymentMethods
        ];
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus(int $paymentId, string $status, ?float $paidAmount = null, ?string $paymentDate = null, ?string $paymentMethod = null): bool
    {
        $updateData = ['status' => $status];

        if ($paidAmount !== null) {
            $updateData['paid_amount'] = $paidAmount;
        }

        if ($paymentDate !== null) {
            $updateData['payment_date'] = $paymentDate;
        }

        if ($paymentMethod !== null) {
            $updateData['payment_method'] = $paymentMethod;
        }

        $updateData['updated_at'] = Carbon::now();

        return DB::table('payment_schedules')
            ->where('id', $paymentId)
            ->update($updateData) > 0;
    }

    /**
     * Get scheduled payments by month for projections
     */
    public function getScheduledPaymentsByMonth(string $startDate, string $endDate, ?int $officeId = null): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->selectRaw('
                DATE_FORMAT(ps.due_date, "%Y-%m") as period,
                SUM(ps.amount) as scheduled_amount
            ')
            ->where('ps.due_date', '>=', $startDate)
            ->where('ps.due_date', '<=', $endDate)
            ->groupBy(DB::raw('DATE_FORMAT(ps.due_date, "%Y-%m")'));

        if ($officeId) {
            $query->where('c.office_id', $officeId);
        }

        $results = $query->get();
        $scheduledPayments = [];

        foreach ($results as $result) {
            $scheduledPayments[$result->period] = (float) $result->scheduled_amount;
        }

        return $scheduledPayments;
    }

    /**
     * Get average collection rate
     */
    public function getAverageCollectionRate(string $startDate, string $endDate, ?int $officeId = null): float
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->where('ps.due_date', '>=', $startDate)
            ->where('ps.due_date', '<=', $endDate)
            ->selectRaw('
                SUM(ps.amount) as total_scheduled,
                SUM(ps.paid_amount) as total_collected
            ');

        if ($officeId) {
            $query->where('c.office_id', $officeId);
        }

        $result = $query->first();

        if (!$result || $result->total_scheduled == 0) {
            return 0.85; // Default collection rate
        }

        return (float) ($result->total_collected / $result->total_scheduled);
    }

    /**
     * Get total overdue amount
     */
    public function getTotalOverdueAmount(?int $officeId = null): float
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->where('ps.status', 'pending')
            ->where('ps.due_date', '<', Carbon::now()->format('Y-m-d'))
            ->selectRaw('SUM(ps.amount - ps.paid_amount) as overdue_amount');

        if ($officeId) {
            $query->where('c.office_id', $officeId);
        }

        $result = $query->first();
        return (float) ($result->overdue_amount ?? 0);
    }

    /**
     * Get payment trends
     */
    public function getPaymentTrends(array $filters = []): array
    {
        // Monthly collection trend
        $monthlyTrend = $this->getMonthlyCollectionTrend($filters);
        
        // Collection efficiency over time
        $efficiencyTrend = $this->getCollectionEfficiencyTrend($filters);
        
        return [
            'monthly_collections' => $monthlyTrend,
            'efficiency_trend' => $efficiencyTrend
        ];
    }

    /**
     * Get collection efficiency metrics
     */
    public function getCollectionEfficiency(array $filters = []): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id');

        $this->applyFilters($query, $filters);

        // On-time payment rate
        $onTimeRate = $query->selectRaw('
            (SUM(CASE WHEN ps.status = "paid" AND ps.payment_date <= ps.due_date THEN 1 ELSE 0 END) / COUNT(*)) * 100 as rate
        ')->first();

        // Average days to collect
        $avgDaysToCollect = $query->selectRaw('
            AVG(DATEDIFF(ps.payment_date, ps.due_date)) as avg_days
        ')->where('ps.status', 'paid')->first();

        // Collection rate by month
        $monthlyRates = $this->getMonthlyCollectionRates($filters);

        return [
            'on_time_payment_rate' => round((float) $onTimeRate->rate, 2),
            'average_days_to_collect' => round((float) ($avgDaysToCollect->avg_days ?? 0), 1),
            'monthly_rates' => $monthlyRates
        ];
    }

    /**
     * Get payment method distribution
     */
    public function getPaymentMethodDistribution(array $filters = []): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->where('ps.status', 'paid');

        $this->applyFilters($query, $filters);

        return $query->select([
                'ps.payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(ps.paid_amount) as total_amount')
            ])
            ->groupBy('ps.payment_method')
            ->orderBy('total_amount', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get clients with overdue payments
     */
    public function getClientsWithOverduePayments(array $filters = []): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->leftJoin('users as advisor', 'c.advisor_id', '=', 'advisor.id')
            ->where('ps.status', 'pending')
            ->where('ps.due_date', '<', Carbon::now()->format('Y-m-d'));

        $this->applyFilters($query, $filters);

        return $query->select([
                'c.client_name',
                'c.contract_number',
                'advisor.name as advisor_name',
                DB::raw('COUNT(*) as overdue_payments'),
                DB::raw('SUM(ps.amount - ps.paid_amount) as total_overdue_amount'),
                DB::raw('MIN(ps.due_date) as oldest_overdue_date'),
                DB::raw('MAX(DATEDIFF(CURDATE(), ps.due_date)) as max_days_overdue')
            ])
            ->groupBy('c.id', 'c.client_name', 'c.contract_number', 'advisor.name')
            ->orderBy('total_overdue_amount', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get client payment summary
     */
    public function getClientPaymentSummary(int $contractId): array
    {
        return DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->where('ps.contract_id', $contractId)
            ->select([
                'ps.*',
                'c.client_name',
                'c.contract_number',
                'c.total_amount as contract_total'
            ])
            ->orderBy('ps.payment_number')
            ->get()
            ->toArray();
    }

    /**
     * Bulk update payment status
     */
    public function bulkUpdatePaymentStatus(array $paymentIds, string $status, ?array $additionalData = null): int
    {
        $updateData = ['status' => $status, 'updated_at' => Carbon::now()];

        if ($additionalData) {
            $updateData = array_merge($updateData, $additionalData);
        }

        return DB::table('payment_schedules')
            ->whereIn('id', $paymentIds)
            ->update($updateData);
    }

    /**
     * Get payment aging report
     */
    public function getPaymentAgingReport(array $filters = []): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->where('ps.status', 'pending');

        $this->applyFilters($query, $filters);

        return $query->selectRaw('
                CASE 
                    WHEN ps.due_date >= CURDATE() THEN "Current"
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 1 AND 30 THEN "1-30 days"
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 31 AND 60 THEN "31-60 days"
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 61 AND 90 THEN "61-90 days"
                    ELSE "90+ days"
                END as aging_bucket,
                COUNT(*) as payment_count,
                SUM(ps.amount - ps.paid_amount) as total_amount
            ')
            ->groupBy(DB::raw('
                CASE 
                    WHEN ps.due_date >= CURDATE() THEN "Current"
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 1 AND 30 THEN "1-30 days"
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 31 AND 60 THEN "31-60 days"
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 61 AND 90 THEN "61-90 days"
                    ELSE "90+ days"
                END
            '))
            ->orderBy(DB::raw('
                CASE 
                    WHEN ps.due_date >= CURDATE() THEN 1
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 1 AND 30 THEN 2
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 31 AND 60 THEN 3
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 61 AND 90 THEN 4
                    ELSE 5
                END
            '))
            ->get()
            ->toArray();
    }

    // Helper methods

    protected function applyFilters($query, array $filters): void
    {
        if (!empty($filters['start_date'])) {
            $query->where('ps.due_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('ps.due_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['advisor_id'])) {
            $query->where('c.advisor_id', $filters['advisor_id']);
        }

        if (!empty($filters['office_id'])) {
            $query->where('c.office_id', $filters['office_id']);
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('ps.status', $filters['status']);
            } else {
                $query->where('ps.status', $filters['status']);
            }
        }
    }

    protected function getPaymentAging(array $filters = []): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->where('ps.status', 'pending');

        $this->applyFilters($query, $filters);

        return $query->selectRaw('
                CASE 
                    WHEN ps.due_date >= CURDATE() THEN "Current"
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 1 AND 30 THEN "1-30 days"
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 31 AND 60 THEN "31-60 days"
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 61 AND 90 THEN "61-90 days"
                    ELSE "90+ days"
                END as aging_bucket,
                COUNT(*) as count,
                SUM(ps.amount - ps.paid_amount) as amount
            ')
            ->groupBy(DB::raw('
                CASE 
                    WHEN ps.due_date >= CURDATE() THEN "Current"
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 1 AND 30 THEN "1-30 days"
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 31 AND 60 THEN "31-60 days"
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 61 AND 90 THEN "61-90 days"
                    ELSE "90+ days"
                END
            '))
            ->get()
            ->toArray();
    }

    protected function getMonthlyCollectionTrend(array $filters = []): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->where('ps.payment_date', '>=', Carbon::now()->subMonths(12)->startOfMonth())
            ->where('ps.status', 'paid');

        $this->applyFilters($query, $filters);

        return $query->selectRaw('
                DATE_FORMAT(ps.payment_date, "%Y-%m") as period,
                DATE_FORMAT(ps.payment_date, "%M %Y") as period_name,
                COUNT(*) as payments_count,
                SUM(ps.paid_amount) as total_collected
            ')
            ->groupBy(DB::raw('DATE_FORMAT(ps.payment_date, "%Y-%m")'))
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    protected function getCollectionEfficiencyTrend(array $filters = []): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->where('ps.due_date', '>=', Carbon::now()->subMonths(12)->startOfMonth());

        $this->applyFilters($query, $filters);

        return $query->selectRaw('
                DATE_FORMAT(ps.due_date, "%Y-%m") as period,
                DATE_FORMAT(ps.due_date, "%M %Y") as period_name,
                (SUM(ps.paid_amount) / SUM(ps.amount)) * 100 as collection_rate,
                AVG(CASE WHEN ps.status = "paid" THEN DATEDIFF(ps.payment_date, ps.due_date) ELSE NULL END) as avg_days_to_collect
            ')
            ->groupBy(DB::raw('DATE_FORMAT(ps.due_date, "%Y-%m")'))
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    protected function getMonthlyCollectionRates(array $filters = []): array
    {
        $query = DB::table('payment_schedules as ps')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.id')
            ->where('ps.due_date', '>=', Carbon::now()->subMonths(6)->startOfMonth());

        $this->applyFilters($query, $filters);

        return $query->selectRaw('
                DATE_FORMAT(ps.due_date, "%Y-%m") as period,
                (SUM(ps.paid_amount) / SUM(ps.amount)) * 100 as collection_rate
            ')
            ->groupBy(DB::raw('DATE_FORMAT(ps.due_date, "%Y-%m")'))
            ->orderBy('period')
            ->get()
            ->toArray();
    }
}