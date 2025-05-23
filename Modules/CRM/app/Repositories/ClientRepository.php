<?php

namespace Modules\CRM\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Client;
use Modules\CRM\Models\Spouse;

class ClientRepository
{
    public function handle() {}

    /**
     * Paginate clients with optional filters.
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return Client::with(['addresses', 'interactions'])
            ->when(
                $filters['search'] ?? null,
                fn($q, $search) =>
                $q->where(
                    fn($q2) =>
                    $q2->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name',  'like', "%{$search}%")
                )
            )
            ->when(
                $filters['type'] ?? null,
                fn($q, $type) =>
                $q->where('type', $type)
            )
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Create a new client and load relations.
     */
    public function create(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            // Crear cliente principal
            $client = Client::create($data);

            // Crear direcciones si vienen anidadas
            if (!empty($data['addresses']) && is_array($data['addresses'])) {
                foreach ($data['addresses'] as $address) {
                    $client->addresses()->create($address);
                }
            }

            // Vincular cónyuge si se proporciona
            if (!empty($data['spouse_id'])) {
                Spouse::firstOrCreate([
                    'client_id'  => $client->client_id,
                    'partner_id' => $data['spouse_id'],
                ]);
            }

            return $client->load(['addresses', 'interactions', 'spouses']);
        });
        }

    /**
     * Update an existing client.
     */
    public function update(Client $client, array $data): Client
    {
        $client->update($data);
        return $client->load(['addresses', 'interactions']);
    }

    /**
     * Delete a client.
     */
    public function delete(Client $client): void
    {
        $client->delete();
    }

    public function all(array $filters = [])
{
    return Client::when($filters['type'] ?? null, fn($q,$t) => $q->where('type',$t))
                 ->when($filters['date_from'] ?? null, fn($q,$d) => $q->whereDate('created_at','>=',$d))
                 ->when($filters['date_to'] ?? null, fn($q,$d) => $q->whereDate('created_at','<=',$d))
                 ->get();
}

    public function addSpouse(Client $client, int $partnerId): Spouse
    {
        return Spouse::create([
            'client_id'  => $client->client_id,
            'partner_id' => $partnerId,
        ]);
    }

    public function removeSpouse(Client $client, int $partnerId): void
    {
        Spouse::query()
            ->where('client_id',  $client->client_id)
            ->where('partner_id', $partnerId)
            ->delete();
    }
}
