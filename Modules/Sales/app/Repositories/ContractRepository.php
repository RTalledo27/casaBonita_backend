<?php

namespace Modules\Sales\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\ContractApproval;
use Modules\Sales\Models\PaymentSchedule;

class ContractRepository
{
    public function paginate(int $perPage = 15)
    {
        return Contract::with(['reservation', 'schedules', 'invoices', 'approvals'])
            ->paginate($perPage);
    }

    public function create(array $data, array $approvers = []): Contract
    {
        /* $schedules = $data['schedules'] ?? [];
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
            */

        /* return DB::transaction(function () use ($data, $approvers) {
            $data['status'] = 'pendiente_aprobacion';
            $contract = Contract::create($data);

            foreach ($approvers as $userId) {
                ContractApproval::create([
                    'contract_id' => $contract->contract_id,
                    'user_id'     => $userId,
                    'status'      => 'pendiente'
                ]);
            }

            return $contract;
        });*/

        $schedules = $data['schedules'] ?? [];
        unset($data['schedules']);

        return DB::transaction(
            function () use ($data, $schedules, $approvers) {
                $data['status'] = 'pendiente_aprobacion';
                $contract = Contract::create($data);

                foreach ($schedules as $sch) {
                    PaymentSchedule::create([
                        'contract_id' => $contract->contract_id,
                        'due_date'    => $sch['due_date'],
                        'amount'      => $sch['amount'],
                        'status'      => 'pendiente',
                    ]);
                }
                foreach ($approvers as $userId) {
                    ContractApproval::create([
                        'contract_id' => $contract->contract_id,
                        'user_id'     => $userId,
                        'status'      => 'pendiente',
                    ]);
                }

                if ($lot = $contract->reservation->lot ?? null) {
                    $lot->update(['status' => 'vendido']);
                }

                return $contract->load(['reservation', 'schedules', 'approvals']);
            }
        );
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
