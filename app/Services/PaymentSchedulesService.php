<?php

namespace App\Services;

use App\Repositories\PaymentsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class PaymentSchedulesService
{
    protected $paymentsRepository;

    public function __construct(PaymentsRepository $paymentsRepository)
    {
        $this->paymentsRepository = $paymentsRepository;
    }

    /**
     * Get payment schedules with filters and pagination
     */
    public function getPaymentSchedules(array $filters, int $page = 1, int $perPage = 15): array
    {
        $cacheKey = 'payment_schedules_' . md5(serialize($filters) . $page . $perPage);
        
        return Cache::remember($cacheKey, 300, function () use ($filters, $page, $perPage) {
            $schedulesData = $this->paymentsRepository->getPaymentSchedulesWithFilters($filters, $page, $perPage);
            
            // Calculate additional metrics
            $overdueCount = $this->paymentsRepository->getOverdueCount($filters);
            $totalPending = $this->paymentsRepository->getTotalPendingAmount($filters);

            return [
                'schedules' => $schedulesData['data'],
                'overdue_count' => $overdueCount,
                'total_pending' => $totalPending,
                'pagination' => $schedulesData['pagination']
            ];
        });
    }

    /**
     * Get overdue payments
     */
    public function getOverduePayments(array $filters, int $page = 1, int $perPage = 15): array
    {
        $cacheKey = 'overdue_payments_' . md5(serialize($filters) . $page . $perPage);
        
        return Cache::remember($cacheKey, 300, function () use ($filters, $page, $perPage) {
            return $this->paymentsRepository->getOverduePayments($filters, $page, $perPage);
        });
    }

    /**
     * Get payment calendar data
     */
    public function getPaymentCalendar(int $year, int $month, ?int $officeId = null): array
    {
        $cacheKey = 'payment_calendar_' . $year . '_' . $month . '_' . ($officeId ?? 'all');
        
        return Cache::remember($cacheKey, 600, function () use ($year, $month, $officeId) {
            return $this->paymentsRepository->getPaymentCalendar($year, $month, $officeId);
        });
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStatistics(array $filters): array
    {
        $cacheKey = 'payment_statistics_' . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 600, function () use ($filters) {
            return $this->paymentsRepository->getPaymentStatistics(
                $filters['start_date'],
                $filters['end_date'],
                $filters['office_id'] ?? null
            );
        });
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus(int $id, array $updateData): array
    {
        // Clear related cache
        Cache::tags(['payment_schedules', 'payment_statistics'])->flush();
        
        $updated = $this->paymentsRepository->updatePaymentStatus($id, $updateData);
        
        // Log the payment status change if needed
        if ($updateData['status'] === 'paid' && !empty($updateData['payment_date'])) {
            $this->logPaymentEvent($id, 'payment_completed', $updateData);
        }
        
        return $updated;
    }

    /**
     * Get payment trends
     */
    public function getPaymentTrends(array $filters, string $period = 'monthly'): array
    {
        $cacheKey = 'payment_trends_' . md5(serialize($filters) . $period);
        
        return Cache::remember($cacheKey, 600, function () use ($filters, $period) {
            return $this->paymentsRepository->getPaymentTrends(
                $filters['start_date'],
                $filters['end_date'],
                $period,
                $filters['office_id'] ?? null
            );
        });
    }

    /**
     * Get collection efficiency metrics
     */
    public function getCollectionEfficiency(array $filters): array
    {
        $cacheKey = 'collection_efficiency_' . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 600, function () use ($filters) {
            $statistics = $this->getPaymentStatistics($filters);
            
            $totalScheduled = $statistics['total_scheduled'] ?? 0;
            $totalCollected = $statistics['total_collected'] ?? 0;
            $totalOverdue = $statistics['total_overdue'] ?? 0;
            
            $collectionRate = $totalScheduled > 0 ? ($totalCollected / $totalScheduled) * 100 : 0;
            $overdueRate = $totalScheduled > 0 ? ($totalOverdue / $totalScheduled) * 100 : 0;
            
            return [
                'collection_rate' => round($collectionRate, 2),
                'overdue_rate' => round($overdueRate, 2),
                'efficiency_score' => round(100 - $overdueRate, 2),
                'total_scheduled' => $totalScheduled,
                'total_collected' => $totalCollected,
                'total_overdue' => $totalOverdue,
                'total_pending' => $totalScheduled - $totalCollected - $totalOverdue
            ];
        });
    }

    /**
     * Get payment method distribution
     */
    public function getPaymentMethodDistribution(array $filters): array
    {
        $cacheKey = 'payment_methods_' . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 600, function () use ($filters) {
            return $this->paymentsRepository->getPaymentMethodDistribution(
                $filters['start_date'],
                $filters['end_date'],
                $filters['office_id'] ?? null
            );
        });
    }

    /**
     * Get clients with overdue payments
     */
    public function getClientsWithOverduePayments(array $filters): array
    {
        $cacheKey = 'clients_overdue_' . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 300, function () use ($filters) {
            return $this->paymentsRepository->getClientsWithOverduePayments($filters);
        });
    }

    /**
     * Generate payment reminders
     */
    public function generatePaymentReminders(int $daysBefore = 7): array
    {
        $upcomingPayments = $this->paymentsRepository->getUpcomingPayments($daysBefore);
        
        $reminders = [];
        foreach ($upcomingPayments as $payment) {
            $reminders[] = [
                'payment_id' => $payment['id'],
                'client_name' => $payment['client_name'],
                'client_email' => $payment['client_email'],
                'amount' => $payment['amount'],
                'due_date' => $payment['due_date'],
                'days_until_due' => Carbon::parse($payment['due_date'])->diffInDays(now()),
                'contract_number' => $payment['contract_number']
            ];
        }
        
        return $reminders;
    }

    /**
     * Get payment schedule summary for a client
     */
    public function getClientPaymentSummary(int $clientId): array
    {
        $cacheKey = 'client_payment_summary_' . $clientId;
        
        return Cache::remember($cacheKey, 300, function () use ($clientId) {
            return $this->paymentsRepository->getClientPaymentSummary($clientId);
        });
    }

    /**
     * Log payment event
     */
    protected function logPaymentEvent(int $paymentId, string $event, array $data): void
    {
        DB::table('payment_events')->insert([
            'payment_schedule_id' => $paymentId,
            'event_type' => $event,
            'event_data' => json_encode($data),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Bulk update payment statuses
     */
    public function bulkUpdatePaymentStatus(array $paymentIds, array $updateData): array
    {
        // Clear related cache
        Cache::tags(['payment_schedules', 'payment_statistics'])->flush();
        
        $results = [];
        foreach ($paymentIds as $paymentId) {
            try {
                $updated = $this->updatePaymentStatus($paymentId, $updateData);
                $results[] = [
                    'payment_id' => $paymentId,
                    'success' => true,
                    'data' => $updated
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'payment_id' => $paymentId,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Get payment aging report
     */
    public function getPaymentAgingReport(array $filters): array
    {
        $cacheKey = 'payment_aging_' . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 600, function () use ($filters) {
            return $this->paymentsRepository->getPaymentAgingReport($filters);
        });
    }
}