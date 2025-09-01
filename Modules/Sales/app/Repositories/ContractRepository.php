<?php

namespace Modules\Sales\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\ContractApproval;
use Modules\Sales\Models\PaymentSchedule;

class ContractRepository
{
    public function paginate(int $perPage = 15, array $filters = [])
    {
        $query = Contract::with([
            'reservation.client', 
            'reservation.lot', 
            'client', // Relación directa para contratos sin reserva
            'lot',    // Relación directa para contratos sin reserva
            'advisor', // Relación con asesor
            'schedules', 
            'invoices', 
            'approvals'
        ]);

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('contract_number', 'LIKE', "%{$search}%")
                  // Búsqueda en campos directos del contrato
                  ->orWhere('client_name', 'LIKE', "%{$search}%")
                  ->orWhere('client_email', 'LIKE', "%{$search}%")
                  ->orWhere('client_phone', 'LIKE', "%{$search}%")
                  // Búsqueda en cliente directo (contratos sin reserva)
                  ->orWhereHas('client', function ($clientQuery) use ($search) {
                      $clientQuery->where('first_name', 'LIKE', "%{$search}%")
                                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                                  ->orWhere('email', 'LIKE', "%{$search}%")
                                  ->orWhere('phone', 'LIKE', "%{$search}%")
                                  ->orWhere('document_number', 'LIKE', "%{$search}%");
                  })
                  // Búsqueda en lote directo (contratos sin reserva)
                  ->orWhereHas('lot', function ($lotQuery) use ($search) {
                      $lotQuery->where('lot_number', 'LIKE', "%{$search}%")
                               ->orWhere('block', 'LIKE', "%{$search}%");
                  })
                  // Búsqueda en reserva (contratos con reserva)
                  ->orWhereHas('reservation', function ($reservationQuery) use ($search) {
                      $reservationQuery->where('client_name', 'LIKE', "%{$search}%")
                                      ->orWhere('client_email', 'LIKE', "%{$search}%")
                                      ->orWhere('client_phone', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply financing filter (contracts with financing_amount > 0)
        if (!empty($filters['with_financing'])) {
            $query->where('financing_amount', '>', 0);
        }

        return $query->paginate($perPage);
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
        return $contract->load([
            'reservation.client', 
            'reservation.lot', 
            'client', 
            'lot', 
            'advisor',
            'schedules', 
            'invoices'
        ]);
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

        /* $schedules = $data['schedules'] ?? [];
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
        ); */

        // Lógica para manejar la reemisión de contratos
        if (isset($data['previous_contract_id']) && $data['previous_contract_id']) {
            $previousContract = $this->find($data['previous_contract_id']);
            if ($previousContract) {
                // Marcar el contrato anterior como 'reemplazado'
                $previousContract->update(['status' => 'reemplazado']);

                // Lógica para calcular el monto transferido del contrato anterior.
                // Esto es un ejemplo y debe ajustarse a tu lógica de negocio exacta.
                // Podría ser la suma de todos los pagos ya realizados en el contrato anterior,
                // o el saldo a favor del cliente, etc.
                if (!isset($data['transferred_amount_from_previous_contract'])) {
                    // Ejemplo: Suma de todos los pagos asociados al contrato anterior
                    // Asumiendo que PaymentSchedule tiene una relación 'payments'
                    $paidAmount = 0;
                    foreach ($previousContract->paymentSchedules as $schedule) {
                        $paidAmount += $schedule->payments()->sum('amount');
                    }
                    $data['transferred_amount_from_previous_contract'] = $paidAmount;
                }
            }
        }

        $contract = Contract::create($data);

        // Aquí podrías añadir lógica para generar el PaymentSchedule inicial
        // si no se hace en otro lugar o si depende de la creación del contrato.
        // Por ejemplo, si el contrato tiene un total_price y se divide en cuotas.
        // if (isset($data['total_price']) && $data['total_price'] > 0) {
        //     // Lógica para crear PaymentSchedule basada en total_price y otros términos
        //     // Esto podría ser un método separado o un servicio
        //     // Ejemplo simple: una sola cuota inicial
        //     PaymentSchedule::create([
        //         'contract_id' => $contract->contract_id,
        //         'due_date' => $contract->sign_date,
        //         'amount' => $data['total_price'],
        //         'status' => 'pendiente',
        //     ]);
        // }

        return $contract->load([
            'reservation.client', 
            'reservation.lot', 
            'client', 
            'lot', 
            'advisor',
            'schedules', 
            'invoices', 
            'approvals'
        ]);
    }

     public function find($id): ?Contract
    {
        return Contract::find($id);
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
