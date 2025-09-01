<?php

namespace Modules\CRM\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'address_id' => $this->address_id,
            'client_id'  => $this->client_id,
            'client_name' => $this->client?->full_name,
            'line1'      => $this->line1,
            'line2'      => $this->line2,
            'city'       => $this->city,
            'state'      => $this->state,
            'country'    => $this->country,
            'zip_code'   => $this->zip_code,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
