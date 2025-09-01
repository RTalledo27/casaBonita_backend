<?php

namespace Modules\Sales\Repositories;

use Modules\Inventory\Models\Lot;
use Modules\Sales\Models\Reservation;

class ReservationRepository
{
    public function paginate(int $perPage = 15)
    {
        return Reservation::with(['lot', 'client', 'advisor', 'contract'])->paginate($perPage);
    }

    public function create(array $data): Reservation
    {
        $reservation = Reservation::create($data);
        if ($lot = Lot::find($data['lot_id'])) {
            $lot->update(['status' => 'reservado']);
        }
        return $reservation->load(['lot', 'client', 'advisor']);
    }

    public function update(Reservation $res, array $data): Reservation
    {
        $res->update($data);
        return $res->load(['lot', 'client', 'advisor', 'contract']);
    }

    public function delete(Reservation $res): void
    {
        $lot = $res->lot;
        $res->delete();
        if ($lot && !$lot->reservations()->exists() && !$lot->contracts()->exists()) {
            $lot->update(['status' => 'disponible']);
        }
    }

    public function confirmPayment(Reservation $reservation, array $data): Reservation
    {
        $reservation->update([
            'deposit_paid_at' => now(),
            'status' => 'confirmada', // O el estado que corresponda después del pago
            'deposit_method' => $data['deposit_method'] ?? $reservation->deposit_method,
            'deposit_reference' => $data['deposit_reference'] ?? $reservation->deposit_reference,
        ]);
        return $reservation->load(['lot', 'client', 'advisor', 'contract']);
    }
}
