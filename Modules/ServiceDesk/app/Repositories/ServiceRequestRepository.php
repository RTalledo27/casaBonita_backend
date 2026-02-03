<?php

namespace Modules\ServiceDesk\Repositories;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\ServiceDesk\Models\ServiceRequest;
use Modules\ServiceDesk\Services\ServiceDeskNotificationService;

class ServiceRequestRepository
{
    protected ?ServiceDeskNotificationService $notificationService = null;

    public function __construct()
    {
        // Lazy load notification service to avoid circular dependencies
        try {
            $this->notificationService = app(ServiceDeskNotificationService::class);
        } catch (\Exception $e) {
            // Service not available, notifications will be skipped
        }
    }

    // Lista paginada con filtros (status, prioridad, tipo, fechas, etc.)
    public function listWithFilters($filters = [])
    {
        $query = ServiceRequest::with(['creator', 'assignee', 'closer', 'actions.user']);

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
        $ticket = ServiceRequest::create($data);
        
        // Notify supervisors for high priority tickets
        if ($this->notificationService) {
            $ticket->load(['creator', 'assignee', 'closer', 'actions.user']);
            $this->notificationService->notifyTicketCreated($ticket);
        }
        
        return $ticket;
    }

    public function find($id)
    {
        return ServiceRequest::findOrFail($id);
    }

    public function findWithRelations($id)
    {
        return ServiceRequest::with(['creator', 'assignee', 'closer', 'actions.user'])->findOrFail($id);
    }

    public function update($id, array $data)
    {
        $ticket = $this->find($id);
        $ticket->update($data);
        return $ticket->fresh(['creator', 'assignee', 'closer', 'actions.user']);
    }

    public function delete($id)
    {
        $ticket = $this->find($id);
        $ticket->delete();
    }

    /**
     * Assign ticket to a technician
     */
    public function assignTicket($ticketId, $userId)
    {
        $ticket = $this->find($ticketId);
        $oldAssignee = $ticket->assigned_to;
        
        $ticket->update([
            'assigned_to' => $userId,
            'status' => $ticket->status === 'abierto' ? 'en_proceso' : $ticket->status,
        ]);

        // Log the action
        $this->logAction($ticketId, auth()->id(), 'assignment', 
            "Ticket asignado a usuario #{$userId}" . ($oldAssignee ? " (antes: #{$oldAssignee})" : "")
        );

        // Send notifications
        if ($this->notificationService) {
            $ticket->load(['creator', 'assignee', 'closer', 'actions.user']);
            $this->notificationService->notifyTicketAssigned($ticket, $userId, $oldAssignee);
        }

        return $ticket->fresh(['creator', 'actions.user', 'assignee']);
    }

    /**
     * Change ticket status with validation
     */
    public function changeStatus($ticketId, $newStatus, $notes = null)
    {
        $validStatuses = ['abierto', 'en_proceso', 'cerrado'];
        if (!in_array($newStatus, $validStatuses)) {
            throw new \InvalidArgumentException("Estado inválido: {$newStatus}");
        }

        $ticket = $this->find($ticketId);
        $oldStatus = $ticket->status;

        if ($oldStatus === $newStatus) {
            return $ticket;
        }

        $updateData = ['status' => $newStatus];
        
        // If closing, set closed timestamp and closer
        if ($newStatus === 'cerrado') {
            $updateData['closed_at'] = now();
            $updateData['closed_by'] = auth()->id();
        }

        $ticket->update($updateData);

        // Log the action
        $this->logAction($ticketId, auth()->id(), 'status_change', 
            "Estado cambiado de '{$oldStatus}' a '{$newStatus}'" . ($notes ? ": {$notes}" : "")
        );

        // Send notifications
        if ($this->notificationService) {
            $ticket->load(['creator', 'assignee', 'closer', 'actions.user']);
            
            if ($newStatus === 'cerrado') {
                $this->notificationService->notifyTicketResolved($ticket, $notes);
            } else {
                $this->notificationService->notifyStatusChanged($ticket, $oldStatus, $newStatus);
            }
        }

        return $ticket->fresh(['creator', 'actions.user', 'assignee', 'closer']);
    }

    /**
     * Escalate a ticket
     */
    public function escalate($ticketId, $reason = null)
    {
        $ticket = $this->find($ticketId);
        $oldPriority = $ticket->priority;

        $ticket->update([
            'escalated_at' => now(),
            'priority' => 'critica', // Escalation raises priority to critical
            'status' => $ticket->status === 'abierto' ? 'en_proceso' : $ticket->status,
        ]);

        // Log the action
        $this->logAction($ticketId, auth()->id(), 'escalation', 
            "Ticket escalado a prioridad crítica (antes: {$oldPriority})" . ($reason ? ". Motivo: {$reason}" : "")
        );

        // Send notifications to supervisors
        if ($this->notificationService) {
            $ticket->load(['creator', 'assignee', 'closer', 'actions.user']);
            $this->notificationService->notifyTicketEscalated($ticket, $reason ?? 'Sin motivo especificado');
        }

        return $ticket->fresh(['creator', 'actions.user', 'assignee']);
    }

    /**
     * Add a comment/note to a ticket
     */
    public function addComment($ticketId, $notes, $actionType = 'comment')
    {
        $this->logAction($ticketId, auth()->id(), $actionType, $notes);
        
        // Send notifications
        if ($this->notificationService) {
            $ticket = $this->findWithRelations($ticketId);
            $this->notificationService->notifyNewComment($ticket, $notes, $actionType);
            return $ticket;
        }
        
        return $this->findWithRelations($ticketId);
    }

    /**
     * Log an action on a ticket
     */
    protected function logAction($ticketId, $userId, $actionType, $notes = null)
    {
        return \Modules\ServiceDesk\Models\ServiceAction::create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'action_type' => $actionType,
            'performed_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Get tickets for auto-assignment (find technician with least open tickets)
     */
    public function findLeastBusyTechnician()
    {
        return \Modules\Security\Models\User::whereHas('roles', function($q) {
                $q->where('name', 'tecnico'); // Adjust role name as needed
            })
            ->withCount(['assignedTickets' => function($q) {
                $q->where('status', '!=', 'cerrado');
            }])
            ->orderBy('assigned_tickets_count', 'asc')
            ->first();
    }

    /**
     * Get tickets with SLA nearing expiry (for scheduled tasks)
     */
    public function getTicketsNearingSlaExpiry($hoursThreshold = 4)
    {
        return ServiceRequest::with(['creator', 'assignee'])
            ->whereNotNull('sla_due_at')
            ->where('status', '!=', 'cerrado')
            ->whereBetween('sla_due_at', [now(), now()->addHours($hoursThreshold)])
            ->get();
    }

    /**
     * Get tickets with expired SLA (for scheduled tasks)
     */
    public function getTicketsWithExpiredSla()
    {
        return ServiceRequest::with(['creator', 'assignee'])
            ->whereNotNull('sla_due_at')
            ->where('status', '!=', 'cerrado')
            ->where('sla_due_at', '<', now())
            ->whereNull('escalated_at') // Not already escalated
            ->get();
    }
}
