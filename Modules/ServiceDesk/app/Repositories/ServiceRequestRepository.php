<?php

namespace Modules\ServiceDesk\Repositories;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\ServiceDesk\Models\ServiceRequest;

class ServiceRequestRepository
{
    // Lista paginada con filtros (status, prioridad, tipo, fechas, etc.)
    public function listWithFilters($filters = [])
    {
        $query = ServiceRequest::with(['creator', 'actions.user']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (!empty($filters['ticket_type'])) {
            $query->where('ticket_type', $filters['ticket_type']);
        }
        if (!empty($filters['opened_by'])) {
            $query->where('opened_by', $filters['opened_by']);
        }
        // Puedes agregar más filtros (fechas, escalado, etc.)

        return $query->orderByDesc('opened_at')->paginate(20);
    }

    public function count()
    {
        return ServiceRequest::count();
    }

    public function countByStatus($status)
    {
        return ServiceRequest::where('status', $status)->count();
    }

    public function countByPriority()
    {
        return ServiceRequest::selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');
    }

    public function create(array $data)
    {
        return ServiceRequest::create($data);
    }

    public function find($id)
    {
        return ServiceRequest::findOrFail($id);
    }

    public function findWithRelations($id)
    {
        return ServiceRequest::with(['creator', 'actions.user'])->findOrFail($id);
    }

    public function update($id, array $data)
    {
        $ticket = $this->find($id);
        $ticket->update($data);
        return $ticket->fresh(['creator', 'actions.user']);
    }

    public function delete($id)
    {
        $ticket = $this->find($id);
        $ticket->delete();
    }
}
