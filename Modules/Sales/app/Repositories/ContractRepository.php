<?php

namespace Modules\Sales\Repositories;

use Modules\Sales\Models\Contract;
use Modules\Sales\Models\PaymentSchedule;

class ContractRepository
{
    public function handle() {}
    public function paginate(int $perPage = 15)
    {
        return Contract::with(['reservation', 'schedules', 'invoices'])->paginate($perPage);
    }

    public function create(array $data): Contract
    {
        $schedules = $data['schedules'] ?? [];
        unset($data['schedules']);

        $contract = Contract::create($data);

        if ($lot = $contract->reservation->lot ?? null) {
            $lot->update(['status' => 'vendido']);
        }

        foreach ($schedules as $sch) {
            PaymentSchedule::create([
                'contract_id' => $contract->contract_id,
                'due_date'    => $sch['due_date'],
                'amount'      => $sch['amount'],
                'status'      => 'pendiente',
            ]);
        }

        return $contract->load(['reservation', 'schedules']);
    }

    public function update(Contract $contract, array $data): Contract
    {
        $contract->update($data);
        return $contract->load(['reservation', 'schedules', 'invoices']);
    }

    public function delete(Contract $contract): void
    {
        $lot = $contract->reservation->lot ?? null;
        $contract->delete();
        if ($lot && !$lot->contracts()->exists()) {
            $lot->update(['status' => $lot->reservations()->exists() ? 'reservado' : 'disponible']);
        }
    }
}
