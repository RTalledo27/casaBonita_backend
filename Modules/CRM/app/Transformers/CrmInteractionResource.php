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
            'interaction_id' => $this->interaction_id,
            'client_id'      => $this->client_id,
            'client_name'    => $this->client->full_name ?? null,
            'user_id'        => $this->user_id,
            'user_name'      => $this->user->name ?? null,
            'date'           => $this->date,
            'channel'        => $this->channel,
            'notes'          => $this->notes,
            'created_at'     => $this->created_at?->toDateTimeString(),
            'updated_at'     => $this->updated_at?->toDateTimeString(),
        ];
        }
}
