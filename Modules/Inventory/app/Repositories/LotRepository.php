<?php

namespace Modules\Inventory\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Inventory\Models\Lot;

class LotRepository
{
    public function handle() {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Lot::with(['manzana', 'streetType', 'media'])
            ->when($filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->when($filters['manzana_id'] ?? null, fn($q, $m) => $q->where('manzana_id', $m))
            ->when($filters['street_type_id'] ?? null, fn($q, $s) => $q->where('street_type_id', $s))
            ->when($filters['search'] ?? null, function($q, $search) {
                $q->where(function($query) use ($search) {
                    $query->where('num_lot', 'like', "%{$search}%")
                          ->orWhereHas('manzana', fn($q) => $q->where('name', 'like', "%{$search}%"))
                          ->orWhereHas('streetType', fn($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->paginate($filters['per_page'] ?? $perPage);
    }

    public function create(array $data): Lot
    {
        return Lot::create($data);
    }

    public function update(Lot $lot, array $data): Lot
    {
        $lot->update($data);
        return $lot->load(['manzana', 'streetType', 'media']);
    }

    public function delete(Lot $lot): void
    {
        if ($lot->reservations()->exists() || $lot->contracts()->exists()) {
            throw new \RuntimeException('Lot has active reservations or contracts');
        }
        $lot->delete();
    }
}
