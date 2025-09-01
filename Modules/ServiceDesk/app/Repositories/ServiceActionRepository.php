<?php

namespace Modules\ServiceDesk\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\ServiceDesk\Models\ServiceAction;

class ServiceActionRepository
{
    public function handle() {}



    // Listar acciones de un ticket
    public function listByTicket($ticket_id)
    {
        return ServiceAction::with('user')
            ->where('ticket_id', $ticket_id)
            ->orderBy('performed_at')
            ->get();
    }

    // Crear nueva acción en un ticket
    public function create(array $data)
    {
        return ServiceAction::create($data);
    }

    // Buscar una acción por ID (si necesitas ver/eliminar)
    public function find($action_id)
    {
        return ServiceAction::findOrFail($action_id);
    }

    // Eliminar acción (si necesitas)
    public function delete($action_id)
    {
        $action = $this->find($action_id);
        $action->delete();
    }
}
