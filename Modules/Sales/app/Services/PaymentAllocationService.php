<?php

namespace Modules\Sales\Services;

use Modules\Sales\Models\Payment;
use Modules\Collections\Models\PaymentSchedule;

class PaymentAllocationService
{
    public function applyPaymentFromSchedule(
        PaymentSchedule $startSchedule,
        string $paymentDate,
        float $amount,
        ?string $method = null,
        ?string $reference = null,
        ?string $notes = null,
        ?int $transactionId = null
    ): array {
        $amount = max(0, (float) $amount);
        $method = $method ?: 'transfer';

        $contractId = (int) $startSchedule->contract_id;
        $startInstallment = (int) ($startSchedule->installment_number ?? 0);
        $startScheduleId = (int) $startSchedule->schedule_id;

        $result = [
            'contract_id' => $contractId,
            'transaction_id' => $transactionId,
            'payment_date' => $paymentDate,
            'method' => $method,
            'requested_amount' => $amount,
            'applied_amount' => 0.0,
            'unapplied_amount' => 0.0,
            'allocations' => [],
            'schedule_ids_touched' => [],
        ];

        if ($amount <= 0) {
            $result['unapplied_amount'] = $amount;
            return $result;
        }

        $schedules = PaymentSchedule::query()
            ->where('contract_id', $contractId)
            ->where('installment_number', '>=', $startInstallment)
            ->orderBy('installment_number')
            ->orderBy('schedule_id')
            ->lockForUpdate()
            ->get();

        $remaining = $amount;

        foreach ($schedules as $schedule) {
            if ($remaining <= 0) {
                break;
            }

            if ($schedule->status === 'pagado') {
                continue;
            }

            $scheduleAmount = (float) $schedule->amount;
            $alreadyPaid = (float) ($schedule->amount_paid ?? 0);
            $scheduleRemaining = max(0, $scheduleAmount - $alreadyPaid);

            if ($scheduleRemaining <= 0) {
                $schedule->update([
                    'status' => 'pagado',
                    'paid_date' => $schedule->paid_date ?? $paymentDate,
                    'payment_date' => $schedule->payment_date ?? $paymentDate,
                    'payment_method' => $schedule->payment_method ?? $method,
                ]);
                continue;
            }

            $applied = min($scheduleRemaining, $remaining);
            if ($applied <= 0) {
                continue;
            }

            $paymentReference = $reference ?: "Pago aplicado a cuota #{$schedule->installment_number}";

            $payment = Payment::create([
                'transaction_id' => $transactionId,
                'schedule_id' => $schedule->schedule_id,
                'contract_id' => $contractId,
                'payment_date' => $paymentDate,
                'amount' => $applied,
                'method' => $method,
                'reference' => $paymentReference,
            ]);

            $newPaid = $alreadyPaid + $applied;
            $nowPaidInFull = $newPaid + 0.00001 >= $scheduleAmount;

            $schedule->update([
                'amount_paid' => $newPaid,
                'payment_date' => $paymentDate,
                'payment_method' => $method,
                'notes' => $schedule->schedule_id === $startScheduleId ? ($notes ?? $schedule->notes) : $schedule->notes,
                'paid_date' => $nowPaidInFull ? $paymentDate : $schedule->paid_date,
                'status' => $nowPaidInFull ? 'pagado' : $schedule->status,
            ]);

            $result['allocations'][] = [
                'payment_id' => (int) $payment->payment_id,
                'schedule_id' => (int) $schedule->schedule_id,
                'installment_number' => (int) $schedule->installment_number,
                'applied_amount' => (float) $applied,
                'schedule_amount' => (float) $scheduleAmount,
                'schedule_amount_paid' => (float) $newPaid,
                'schedule_status' => (string) ($nowPaidInFull ? 'pagado' : $schedule->status),
            ];
            $result['schedule_ids_touched'][] = (int) $schedule->schedule_id;

            $remaining -= $applied;
            $result['applied_amount'] += $applied;
        }

        $result['unapplied_amount'] = max(0, $remaining);

        return $result;
    }
}
