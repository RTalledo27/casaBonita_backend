<?php

namespace Modules\CRM\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->address_id,
            'client_id' => $this->client_id,
            'line1'     => $this->line1,
            'line2'     => $this->line2,
            'city'      => $this->city,
            'state'     => $this->state,
            'country'   => $this->country,
            'zip_code'  => $this->zip_code,
        ];
    }
}
