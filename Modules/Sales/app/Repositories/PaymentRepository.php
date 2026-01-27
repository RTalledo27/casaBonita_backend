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
        // Lógica adicional después de crear el pago, por ejemplo, actualizar el estado del PaymentSchedule
        // if ($payment->schedule) {
        //     $payment->schedule->update(['status' => 'pagado']); // O lógica más compleja si hay pagos parciales
        // }
        return $payment;
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
