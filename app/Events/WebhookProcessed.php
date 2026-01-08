<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebhookProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data;

    /**
     * Create a new event instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('webhooks');
    }

    /**
     * Nombre del evento para el frontend
     */
    public function broadcastAs(): string
    {
        return 'webhook.processed';
    }

    /**
     * Datos que se enviarán al frontend
     */
    public function broadcastWith(): array
    {
        return [
            'messageId' => $this->data['messageId'],
            'eventType' => $this->data['eventType'],
            'eventTimestamp' => $this->data['eventTimestamp'],
            'message' => $this->data['message'],
            'type' => $this->data['type'],
            'data' => $this->data['data'] ?? [],
            'timestamp' => now()->toIso8601String()
        ];
    }
}
