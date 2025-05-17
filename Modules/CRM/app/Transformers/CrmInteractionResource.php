<?php

namespace Modules\CRM\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmInteractionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->interaction_id,
            'client_id' => $this->client_id,
            'user_id' => $this->user_id,
            'channel' => $this->channel,
            'notes' => $this->notes,
            'date' => $this->date, // ← CAMBIADO
            'created_at' => $this->created_at,
        ];
        }
}
