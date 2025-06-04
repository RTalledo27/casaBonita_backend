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
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Client::with(['addresses', 'interactions'])
            ->filter($filters)
            ->paginate($filters['per_page'] ?? $perPage);
    }


    /**
     * Create a new client and load relations.
     */
    public function create(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            $client = Client::create($data);

            if (!empty($data['addresses']) && is_array($data['addresses'])) {
                foreach ($data['addresses'] as $address) {
                    $client->addresses()->create($address);
                }
            }


            if (!empty($data['family_members']) && is_array($data['family_members'])) {
                foreach ($data['family_members'] as $member) {
                    $client->familyMembers()->create($member);
                }
            }





            if (!empty($data['spouse_id'])) {
                Spouse::firstOrCreate([
                    'client_id' => $client->client_id,
                    'partner_id' => $data['spouse_id'],
                ]);
            }

            return $client->load(['addresses', 'interactions', 'spouses', 'familyMembers']);
        });
    }

    /**
     * Update an existing client.
     */
    public function update(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($client, $data) {
            $client->update($data);

            if (array_key_exists('family_members', $data)) {
                $client->familyMembers()->delete();
                foreach ($data['family_members'] as $member) {
                    $client->familyMembers()->create($member);
                }
            }

            return $client->load(['addresses', 'interactions', 'familyMembers']);
        });
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
