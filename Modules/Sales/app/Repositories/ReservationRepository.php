<?php

namespace Modules\Sales\Repositories;

use Modules\Inventory\Models\Lot;
use Modules\Sales\Models\Reservation;

class ReservationRepository
{
    public function paginate(int $perPage = 15, array $filters = [])
    {
        $query = Reservation::with(['lot', 'client', 'advisor', 'contract']);

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->orWhere('reservation_id', (int) $search);
                }

                $q->orWhereHas('client', function ($clientQuery) use ($search) {
                    $clientQuery
                        ->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhere('doc_number', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('lot', function ($lotQuery) use ($search) {
                    $lotQuery->where('num_lot', 'LIKE', "%{$search}%");
                });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['lot_id'])) {
            $query->where('lot_id', $filters['lot_id']);
        }

        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (!empty($filters['advisor_id'])) {
            $query->where('advisor_id', $filters['advisor_id']);
        }

        if (!empty($filters['reservation_date_from'])) {
            $query->whereDate('reservation_date', '>=', $filters['reservation_date_from']);
        }

        if (!empty($filters['reservation_date_to'])) {
            $query->whereDate('reservation_date', '<=', $filters['reservation_date_to']);
        }

        return $query->paginate($perPage);
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
