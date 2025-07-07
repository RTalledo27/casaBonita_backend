<?php

namespace Modules\ServiceDesk\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Security\Transformers\UserResource;

class ServiceActionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'action_id'         => $this->action_id,
            'ticket_id'         => $this->ticket_id,
            'user'              => new UserResource($this->whenLoaded('user')),
            'action_type'       => $this->action_type,
            'performed_at'      => $this->performed_at?->toIso8601String(),
            'notes'             => $this->notes,
            'next_action_date'  => $this->next_action_date?->toDateString(),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
            'deleted_at'        => $this->deleted_at?->toIso8601String(),
        ];
    }
}
