<?php

namespace Modules\Reports\Services;

use Modules\Reports\Repositories\PaymentsRepository;
use Carbon\Carbon;

class PaymentSchedulesService
{
    protected $paymentsRepository;

    public function __construct(PaymentsRepository $paymentsRepository)
    {
        $this->paymentsRepository = $paymentsRepository;
    }

    /**
     * Get payment schedules overview
     */
    public function getOverview($dateFrom = null, $dateTo = null, $status = null)
    {
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now()->startOfMonth();
        $dateTo = $dateTo ? Carbon::parse($dateTo) : Carbon::now()->endOfMonth();

        // Check if we have any data in the payment_schedules table
        $hasData = \DB::table('payment_schedules')->count() > 0;
        
        if (!$hasData) {
            // Return mock data when no real data exists
            return $this->getMockOverviewData();
        }

        return [
            'summary' => $this->getPaymentsSummary($dateFrom, $dateTo, $status),
            'status_breakdown' => $this->getStatusBreakdown($dateFrom, $dateTo),
            'upcoming_payments' => $this->getUpcomingPayments(7),
            'overdue_summary' => $this->getOverdueSummary()
        ];
    }

    /**
     * Get mock overview data when no real data exists
     */
    private function getMockOverviewData()
    {
        return [
            'summary' => [
                'total_schedules' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'pending_amount' => 0,
                'overdue_amount' => 0
            ],
            'status_breakdown' => [
                'pending' => 0,
                'paid' => 0,
                'overdue' => 0,
                'cancelled' => 0
            ],
            'upcoming_payments' => [],
            'overdue_summary' => [
                'count' => 0,
                'total_amount' => 0,
                'average_days_overdue' => 0
            ]
        ];
    }

    /**
     * Get payment schedules by status
     */
    public function getByStatus($status, $dateFrom = null, $dateTo = null, $clientId = null, $page = 1, $perPage = 20)
    {
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : null;
        $dateTo = $dateTo ? Carbon::parse($dateTo) : null;

        return $this->paymentsRepository->getByStatus($status, $dateFrom, $dateTo, $clientId, $page, $perPage);
    }

    /**
     * Get overdue payments
     */
    public function getOverdue($daysOverdue = null, $clientId = null, $page = 1, $perPage = 20)
    {
        return $this->paymentsRepository->getOverdue($daysOverdue, $clientId, $page, $perPage);
    }

    /**
     * Get payment trends
     */
    public function getPaymentTrends($period, $dateFrom = null, $dateTo = null)
    {
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now()->subMonths(6);
        $dateTo = $dateTo ? Carbon::parse($dateTo) : Carbon::now();

        return $this->paymentsRepository->getPaymentTrends($period, $dateFrom, $dateTo);
    }

    /**
     * Get collection efficiency
     */
    public function getCollectionEfficiency($dateFrom = null, $dateTo = null, $employeeId = null)
    {
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now()->startOfMonth();
        $dateTo = $dateTo ? Carbon::parse($dateTo) : Carbon::now()->endOfMonth();

        return $this->paymentsRepository->getCollectionEfficiency($dateFrom, $dateTo, $employeeId);
    }

    /**
     * Get upcoming payments
     */
    public function getUpcoming($daysAhead = 30, $clientId = null, $page = 1, $perPage = 20)
    {
        return $this->paymentsRepository->getUpcoming($daysAhead, $clientId, $page, $perPage);
    }

    /**
     * Get payments summary
     */
    private function getPaymentsSummary($dateFrom, $dateTo, $status)
    {
        return $this->paymentsRepository->getPaymentsSummary($dateFrom, $dateTo, $status);
    }

    /**
     * Get status breakdown
     */
    private function getStatusBreakdown($dateFrom, $dateTo)
    {
        return $this->paymentsRepository->getStatusBreakdown($dateFrom, $dateTo);
    }

    /**
     * Get upcoming payments (private method for overview)
     */
    private function getUpcomingPayments($days)
    {
        return $this->paymentsRepository->getUpcoming($days, null, 1, 5);
    }

    /**
     * Get overdue summary
     */
    private function getOverdueSummary()
    {
        return $this->paymentsRepository->getOverdueSummary();
    }
}