<?php

namespace Modules\Sales\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Sales\Models\PaymentSchedule;

class PaymentScheduleRepository
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return PaymentSchedule::with(['contract', 'payments'])->paginate($perPage);
    }

    public function create(array $data): PaymentSchedule
    {
        $schedule = PaymentSchedule::create($data);
        return $schedule->load(['contract', 'payments']);
    }

    public function update(PaymentSchedule $schedule, array $data): PaymentSchedule
    {
        $schedule->update($data);
        return $schedule->load(['contract', 'payments']);
    }

    public function delete(PaymentSchedule $schedule): void
    {
        $schedule->delete();
    }
}
