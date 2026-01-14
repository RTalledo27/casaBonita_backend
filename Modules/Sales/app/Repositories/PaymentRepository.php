<?php

namespace Modules\Sales\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Modules\Sales\Models\Payment;
use Modules\Sales\Models\PaymentSchedule;

class PaymentRepository
{

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Payment::with(['schedule', 'journalEntry'])->paginate($perPage);
    }

    public function create(array $data): Payment
    {
        if (Schema::hasColumn('payments', 'contract_id') && empty($data['contract_id']) && !empty($data['schedule_id'])) {
            $schedule = PaymentSchedule::find($data['schedule_id']);
            if ($schedule) {
                $data['contract_id'] = $schedule->contract_id;
            }
        }

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
