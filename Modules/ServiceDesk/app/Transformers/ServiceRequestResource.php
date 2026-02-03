<?php

namespace Modules\ServiceDesk\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Sales\Transformers\ContractResource;
use Modules\Security\Transformers\UserResource;

class ServiceRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'ticket_id'     => $this->ticket_id,
            'contract_id'   => $this->contract_id,
            'opened_by'     => new UserResource($this->whenLoaded('creator')),
            'assigned_to'   => new UserResource($this->whenLoaded('assignee')),
            'closed_by'     => new UserResource($this->whenLoaded('closer')),
            'opened_at'     => $this->opened_at?->toIso8601String(),
            'ticket_type'   => $this->ticket_type,
            'priority'      => $this->priority,
            'status'        => $this->status,
            'description'   => $this->description,
            'sla_due_at'    => $this->sla_due_at?->toIso8601String(),
            'escalated_at'  => $this->escalated_at?->toIso8601String(),
            'closed_at'     => $this->closed_at?->toIso8601String(),
            'actions'       => ServiceActionResource::collection($this->whenLoaded('actions')),
            // Timestamps
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
            'deleted_at'    => $this->deleted_at?->toIso8601String(),
        ];
    }
}
