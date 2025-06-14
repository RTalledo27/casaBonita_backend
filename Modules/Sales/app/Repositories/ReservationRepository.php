<?php

namespace Modules\Sales\Repositories;

use Modules\Inventory\Models\Lot;
use Modules\Sales\Models\Reservation;

class ReservationRepository
{
    public function handle() {}
    public function paginate(int $perPage = 15)
    {
        return Reservation::with(['lot', 'client', 'contract'])->paginate($perPage);
    }

    public function create(array $data): Reservation
    {
        $reservation = Reservation::create($data);
        if ($lot = Lot::find($data['lot_id'])) {
            $lot->update(['status' => 'reservado']);
        }
        return $reservation->load(['lot', 'client']);
    }

    public function update(Reservation $res, array $data): Reservation
    {
        $res->update($data);
        return $res->load(['lot', 'client', 'contract']);
    }

    public function delete(Reservation $res): void
    {
        $lot = $res->lot;
        $res->delete();
        if ($lot && !$lot->reservations()->exists() && !$lot->contracts()->exists()) {
            $lot->update(['status' => 'disponible']);
        }
    }
}
