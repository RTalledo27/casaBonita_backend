<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class LotStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public int $lot_id;
    public string $status;
    public ?string $previous_status;
    public ?int $locked_by;
    public ?string $locked_by_name;
    public ?string $lock_reason;
    public ?string $manzana_name;
    public ?string $num_lot;
    public string $timestamp;

    public function __construct(array $data)
    {
        $this->lot_id = $data['lot_id'];
        $this->status = $data['status'];
        $this->previous_status = $data['previous_status'] ?? null;
        $this->locked_by = $data['locked_by'] ?? null;
        $this->locked_by_name = $data['locked_by_name'] ?? null;
        $this->lock_reason = $data['lock_reason'] ?? null;
        $this->manzana_name = $data['manzana_name'] ?? null;
        $this->num_lot = $data['num_lot'] ?? null;
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('lots'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'lot.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'lot_id' => $this->lot_id,
            'status' => $this->status,
            'previous_status' => $this->previous_status,
            'locked_by' => $this->locked_by,
            'locked_by_name' => $this->locked_by_name,
            'lock_reason' => $this->lock_reason,
            'manzana_name' => $this->manzana_name,
            'num_lot' => $this->num_lot,
            'timestamp' => $this->timestamp,
        ];
    }
}
