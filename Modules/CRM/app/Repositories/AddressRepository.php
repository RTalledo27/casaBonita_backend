<?php

namespace Modules\CRM\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\CRM\Models\Address;

class AddressRepository
{
    public function handle() {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return Address::with('client')
            ->when($filters['client_id'] ?? null, fn($q, $id) => $q->where('client_id', $id))
            ->orderBy($filters['sort_by'] ?? 'address_id', $filters['sort_dir'] ?? 'asc')
            ->paginate($filters['per_page'] ?? 20);
    }


    public function find(int $id): Address
    {
        return Address::with('client')->findOrFail($id);
    }



    public function create(array $data): Address
    {
        return Address::create($data)->load('client');
    }

    /**
     * Actualiza un Address existente.
     */
    public function update(Address $address, array $data): Address
    {
        $address->update($data);
        return $address->load('client');
    }

    /**
     * Elimina un Address.
     */
    public function delete(Address $address): void
    {
        $address->delete();
    }


    public function belongsToClient(Address $address, int $clientId): bool
    {
        return $address->client_id === $clientId;
    }
}
