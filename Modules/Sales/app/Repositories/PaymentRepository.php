<?php

namespace Modules\Sales\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Sales\Models\Payment;

class PaymentRepository
{

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Payment::with(['schedule', 'journalEntry'])->paginate($perPage);
    }

    public function create(array $data): Payment
    {
        $payment = Payment::create($data);
        return $payment->load(['schedule', 'journalEntry']);
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);
        return $payment->load(['schedule', 'journalEntry']);
    }

    public function delete(Payment $payment): void
    {
        $payment->delete();
    }

}
