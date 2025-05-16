<?php
// Modules/CRM/Http/Resources/ClientResource.php
namespace Modules\CRM\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Transformers\CrmInteractionResource;

class ClientResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->client_id,
            'full_name'   => "{$this->first_name} {$this->last_name}",
            'doc'         => "{$this->doc_type}-{$this->doc_number}",
            'email'       => $this->email,
            'phones'      => [
                'primary'   => $this->primary_phone,
                'secondary' => $this->secondary_phone,
            ],
            'type'        => $this->type,
            'addresses'   => AddressResource::collection($this->addresses),
            'interactions' => CrmInteractionResource::collection($this->interactions),
            'created_at'  => $this->created_at->toDateTimeString(),
        ];
    }
}
