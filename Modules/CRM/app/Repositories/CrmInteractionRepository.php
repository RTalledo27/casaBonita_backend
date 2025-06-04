<?php

namespace Modules\CRM\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CRM\Models\CrmInteraction;
use Illuminate\Pagination\LengthAwarePaginator;

class CrmInteractionRepository
{
    public function all(): Collection
    {
        return CrmInteraction::with(['client', 'user'])->get();
    }

    /**
     * Paginación con filtros opcionales.
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = CrmInteraction::with(['client', 'user']);

        if (!empty($filters['search'])) {
            $query->where('notes', 'like', "%{$filters['search']}%");
        }

        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        return $query->orderBy('date', 'desc')
            ->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Crear nueva interacción.
     */
    public function create(array $data): CrmInteraction
    {
        return CrmInteraction::create($data);
    }

    /**
     * Buscar una interacción por ID.
     */
    public function find(int $id): CrmInteraction
    {
        return CrmInteraction::with(['client', 'user'])->findOrFail($id);
    }

    /**
     * Actualizar una interacción.
     */
    public function update(CrmInteraction $interaction, array $data): CrmInteraction
    {
        $interaction->update($data);
        return $interaction->refresh();
    }

    /**
     * Eliminar una interacción.
     */
    public function delete(CrmInteraction $interaction): void
    {
        $interaction->delete();
    }
}
