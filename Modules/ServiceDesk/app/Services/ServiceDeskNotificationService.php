<?php

namespace Modules\ServiceDesk\Services;

use App\Services\NotificationService;
use Modules\Audit\Models\AuditLog;
use Modules\Security\Models\User;
use Modules\ServiceDesk\Models\ServiceRequest;

class ServiceDeskNotificationService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Notify technician when a ticket is assigned to them
     */
    public function notifyTicketAssigned(ServiceRequest $ticket, int $technicianUserId, ?int $oldAssigneeId = null): void
    {
        $ticketUrl = "/service-desk/tickets/{$ticket->ticket_id}";
        
        // Notify new assignee
        $this->notificationService->create([
            'user_id' => $technicianUserId,
            'type' => 'info',
            'priority' => $ticket->priority === 'critica' ? 'high' : 'medium',
            'title' => 'Ticket Asignado',
            'message' => "Se te ha asignado el ticket #{$ticket->ticket_id}: {$ticket->description}",
            'related_module' => 'service_desk',
            'related_id' => $ticket->ticket_id,
            'related_url' => $ticketUrl,
            'icon' => 'user-check',
        ]);

        // Notify old assignee if exists
        if ($oldAssigneeId && $oldAssigneeId !== $technicianUserId) {
            $this->notificationService->create([
                'user_id' => $oldAssigneeId,
                'type' => 'info',
                'priority' => 'low',
                'title' => 'Ticket Reasignado',
                'message' => "El ticket #{$ticket->ticket_id} ha sido reasignado a otro técnico",
                'related_module' => 'service_desk',
                'related_id' => $ticket->ticket_id,
                'related_url' => $ticketUrl,
                'icon' => 'user-minus',
            ]);
        }

        // Log audit
        $this->logAudit('ticket_assigned', $ticket, [
            'old_assignee' => $oldAssigneeId,
            'new_assignee' => $technicianUserId,
        ]);
    }

    /**
     * Notify supervisors and ticket creator when ticket is escalated
     */
    public function notifyTicketEscalated(ServiceRequest $ticket, string $reason): void
    {
        $ticketUrl = "/service-desk/tickets/{$ticket->ticket_id}";

        // Notify users with escalation permission (flexible - works with any role)
        // Permission: 'service-desk.receive-escalations'
        $recipientIds = User::permission('service-desk.receive-escalations')
            ->pluck('user_id');

        foreach ($recipientIds as $userId) {
            $this->notificationService->create([
                'user_id' => $userId,
                'type' => 'warning',
                'priority' => 'high',
                'title' => '⚠️ Ticket Escalado',
                'message' => "El ticket #{$ticket->ticket_id} ha sido escalado. Motivo: {$reason}",
                'related_module' => 'service_desk',
                'related_id' => $ticket->ticket_id,
                'related_url' => $ticketUrl,
                'icon' => 'alert-triangle',
            ]);
        }

        // Notify ticket creator
        if ($ticket->opened_by) {
            $this->notificationService->create([
                'user_id' => $ticket->opened_by,
                'type' => 'info',
                'priority' => 'medium',
                'title' => 'Tu Ticket fue Escalado',
                'message' => "Tu ticket #{$ticket->ticket_id} ha sido escalado para atención prioritaria",
                'related_module' => 'service_desk',
                'related_id' => $ticket->ticket_id,
                'related_url' => $ticketUrl,
                'icon' => 'arrow-up-circle',
            ]);
        }

        // Log audit
        $this->logAudit('ticket_escalated', $ticket, [
            'reason' => $reason,
            'escalated_by' => auth()->id(),
        ]);
    }

    /**
     * Notify ticket creator when their ticket is resolved
     */
    public function notifyTicketResolved(ServiceRequest $ticket, ?string $resolution = null): void
    {
        if (!$ticket->opened_by) return;

        $ticketUrl = "/service-desk/tickets/{$ticket->ticket_id}";

        $this->notificationService->create([
            'user_id' => $ticket->opened_by,
            'type' => 'success',
            'priority' => 'medium',
            'title' => '✅ Ticket Resuelto',
            'message' => "Tu ticket #{$ticket->ticket_id} ha sido marcado como resuelto" . 
                         ($resolution ? ": {$resolution}" : ""),
            'related_module' => 'service_desk',
            'related_id' => $ticket->ticket_id,
            'related_url' => $ticketUrl,
            'icon' => 'check-circle',
        ]);

        // Log audit
        $this->logAudit('ticket_resolved', $ticket, [
            'resolution' => $resolution,
            'resolved_by' => auth()->id(),
        ]);
    }

    /**
     * Notify relevant parties when ticket status changes
     */
    public function notifyStatusChanged(ServiceRequest $ticket, string $oldStatus, string $newStatus): void
    {
        $ticketUrl = "/service-desk/tickets/{$ticket->ticket_id}";
        $statusLabels = [
            'abierto' => 'Abierto',
            'en_proceso' => 'En Proceso',
            'cerrado' => 'Cerrado',
        ];

        $recipients = array_filter([
            $ticket->opened_by,
            $ticket->assigned_to,
        ]);

        foreach (array_unique($recipients) as $userId) {
            $this->notificationService->create([
                'user_id' => $userId,
                'type' => 'info',
                'priority' => 'low',
                'title' => 'Estado de Ticket Actualizado',
                'message' => "El ticket #{$ticket->ticket_id} cambió de '{$statusLabels[$oldStatus]}' a '{$statusLabels[$newStatus]}'",
                'related_module' => 'service_desk',
                'related_id' => $ticket->ticket_id,
                'related_url' => $ticketUrl,
                'icon' => 'refresh-cw',
            ]);
        }

        // Log audit
        $this->logAudit('ticket_status_changed', $ticket, [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);
    }

    /**
     * Notify assigned technician when a new comment is added
     */
    public function notifyNewComment(ServiceRequest $ticket, string $comment, string $actionType = 'comentario'): void
    {
        $ticketUrl = "/service-desk/tickets/{$ticket->ticket_id}";
        $currentUserId = auth()->id();

        // Notify assignee if comment is from someone else
        if ($ticket->assigned_to && $ticket->assigned_to !== $currentUserId) {
            $this->notificationService->create([
                'user_id' => $ticket->assigned_to,
                'type' => 'info',
                'priority' => 'medium',
                'title' => 'Nuevo Comentario en Ticket',
                'message' => "Nuevo comentario en ticket #{$ticket->ticket_id}: " . substr($comment, 0, 100) . (strlen($comment) > 100 ? '...' : ''),
                'related_module' => 'service_desk',
                'related_id' => $ticket->ticket_id,
                'related_url' => $ticketUrl,
                'icon' => 'message-circle',
            ]);
        }

        // Notify creator if comment is from someone else
        if ($ticket->opened_by && $ticket->opened_by !== $currentUserId) {
            $this->notificationService->create([
                'user_id' => $ticket->opened_by,
                'type' => 'info',
                'priority' => 'low',
                'title' => 'Nuevo Comentario en Tu Ticket',
                'message' => "Tu ticket #{$ticket->ticket_id} tiene un nuevo comentario",
                'related_module' => 'service_desk',
                'related_id' => $ticket->ticket_id,
                'related_url' => $ticketUrl,
                'icon' => 'message-circle',
            ]);
        }

        // Log audit
        $this->logAudit('ticket_comment_added', $ticket, [
            'action_type' => $actionType,
            'comment_preview' => substr($comment, 0, 200),
        ]);
    }

    /**
     * Notify assigned technician when SLA is about to expire
     */
    public function notifySlaNearExpiry(ServiceRequest $ticket, int $hoursRemaining): void
    {
        if (!$ticket->assigned_to) return;

        $ticketUrl = "/service-desk/tickets/{$ticket->ticket_id}";

        $this->notificationService->create([
            'user_id' => $ticket->assigned_to,
            'type' => 'warning',
            'priority' => 'high',
            'title' => '⏰ SLA Próximo a Vencer',
            'message' => "El ticket #{$ticket->ticket_id} vence en {$hoursRemaining} horas. ¡Requiere atención urgente!",
            'related_module' => 'service_desk',
            'related_id' => $ticket->ticket_id,
            'related_url' => $ticketUrl,
            'icon' => 'clock',
        ]);
    }

    /**
     * Notify when SLA has expired
     */
    public function notifySlaExpired(ServiceRequest $ticket): void
    {
        $ticketUrl = "/service-desk/tickets/{$ticket->ticket_id}";
        $recipients = [];

        if ($ticket->assigned_to) $recipients[] = $ticket->assigned_to;

        // Also notify users with SLA oversight permission
        $oversightUsers = User::permission('service-desk.receive-escalations')
            ->pluck('user_id')
            ->toArray();

        $recipients = array_unique(array_merge($recipients, $oversightUsers));

        foreach ($recipients as $userId) {
            $this->notificationService->create([
                'user_id' => $userId,
                'type' => 'error',
                'priority' => 'high',
                'title' => '🚨 SLA Vencido',
                'message' => "El ticket #{$ticket->ticket_id} ha superado su tiempo de SLA",
                'related_module' => 'service_desk',
                'related_id' => $ticket->ticket_id,
                'related_url' => $ticketUrl,
                'icon' => 'alert-octagon',
            ]);
        }

        // Log audit
        $this->logAudit('ticket_sla_expired', $ticket, [
            'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
        ]);
    }

    /**
     * Notify when a new ticket is created (to supervisors/admins for visibility)
     */
    public function notifyTicketCreated(ServiceRequest $ticket): void
    {
        $ticketUrl = "/service-desk/tickets/{$ticket->ticket_id}";

        // Only notify for high priority tickets
        if (!in_array($ticket->priority, ['alta', 'critica'])) {
            // Just log audit for lower priority tickets
            $this->logAudit('ticket_created', $ticket, []);
            return;
        }

        // Notify users with high-priority ticket visibility permission
        $recipientIds = User::permission('service-desk.receive-high-priority')
            ->pluck('user_id');

        foreach ($recipientIds as $userId) {
            $this->notificationService->create([
                'user_id' => $userId,
                'type' => $ticket->priority === 'critica' ? 'error' : 'warning',
                'priority' => 'high',
                'title' => 'Nuevo Ticket de Alta Prioridad',
                'message' => "Se creó el ticket #{$ticket->ticket_id} con prioridad {$ticket->priority}",
                'related_module' => 'service_desk',
                'related_id' => $ticket->ticket_id,
                'related_url' => $ticketUrl,
                'icon' => 'plus-circle',
            ]);
        }

        // Log audit
        $this->logAudit('ticket_created', $ticket, [
            'priority' => $ticket->priority,
            'type' => $ticket->ticket_type,
        ]);
    }

    /**
     * Log action to audit system
     */
    protected function logAudit(string $action, ServiceRequest $ticket, array $changes): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'entity' => 'service_requests',
                'entity_id' => $ticket->ticket_id,
                'timestamp' => now(),
                'changes' => json_encode($changes),
            ]);
        } catch (\Exception $e) {
            // Log but don't fail if audit fails
            \Log::warning("Failed to create audit log: {$e->getMessage()}");
        }
    }
}
