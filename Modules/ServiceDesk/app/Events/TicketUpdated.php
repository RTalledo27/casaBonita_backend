<?php

namespace Modules\ServiceDesk\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\ServiceDesk\Models\ServiceRequest;

/**
 * Event that broadcasts ticket updates in real-time.
 * Uses ShouldBroadcastNow to bypass queues for immediate delivery.
 */
class TicketUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public int $ticketId;
    public string $ticketType;
    public string $status;
    public string $priority;
    public string $action;
    public ?int $openedBy;
    public ?int $assignedTo;

    /**
     * Create a new event instance.
     */
    public function __construct(ServiceRequest $ticket, string $action = 'updated')
    {
        // Store only primitive values to avoid serialization issues
        $this->ticketId = $ticket->ticket_id;
        $this->ticketType = $ticket->ticket_type ?? 'otro';
        $this->status = $ticket->status ?? 'abierto';
        $this->priority = $ticket->priority ?? 'media';
        $this->action = $action;
        $this->openedBy = $ticket->opened_by;
        $this->assignedTo = $ticket->assigned_to;
    }

    /**
     * Get the channels the event should broadcast on.
     * Using public channels for simplicity (no auth required)
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Broadcast to the ticket opener's channel
        if ($this->openedBy) {
            $channels[] = new Channel('servicedesk.' . $this->openedBy);
        }

        // Broadcast to assigned technician's channel
        if ($this->assignedTo && $this->assignedTo !== $this->openedBy) {
            $channels[] = new Channel('servicedesk.' . $this->assignedTo);
        }

        // Also broadcast to a global servicedesk channel for admins
        $channels[] = new Channel('servicedesk.updates');

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ticket.' . $this->action;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'ticket_type' => $this->ticketType,
            'status' => $this->status,
            'priority' => $this->priority,
            'action' => $this->action,
            'opened_by' => $this->openedBy,
            'assigned_to' => $this->assignedTo,
        ];
    }
}
