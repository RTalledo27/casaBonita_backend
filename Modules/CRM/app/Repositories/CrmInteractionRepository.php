<?php

namespace Modules\CRM\Repositories;

use Modules\CRM\Models\CrmInteraction;
use Illuminate\Pagination\LengthAwarePaginator;

class CrmInteractionRepository
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return CrmInteraction::with(['client', 'user'])
            ->when($filters['client_id'] ?? null, fn($q, $id) => $q->where('client_id', $id))
            ->orderBy($filters['sort_by'] ?? 'date', $filters['sort_dir'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function create(array $data): CrmInteraction
    {
        $data['user_id'] = auth()->id(); 

        return CrmInteraction::create($data)->load(['client', 'user']);
    }

    public function update(CrmInteraction $interaction, array $data): CrmInteraction
    {
        $interaction->update($data);
        return $interaction->load(['client', 'user']);
    }

    public function delete(CrmInteraction $interaction): void
    {
        $interaction->delete();
    }
}
