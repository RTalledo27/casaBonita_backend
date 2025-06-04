<?php
// Modules/CRM/Http/Resources/ClientResource.php
namespace Modules\CRM\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Transformers\CrmInteractionResource;
use Modules\CRM\Transformers\AddressResource;


class ClientResource extends JsonResource
{
    public function toArray($request): array
    {
        /*return [
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
        ];*/

        return [
            'client_id'        => $this->client_id,
            'first_name'       => $this->first_name,
            'last_name'        => $this->last_name,
            'doc_type'         => $this->doc_type,
            'doc_number'       => $this->doc_number,
            'email'            => $this->email,
            'primary_phone'    => $this->primary_phone,
            'secondary_phone'  => $this->secondary_phone,
            'marital_status'   => $this->marital_status,
            'type'             => $this->type,
            'date'             => $this->date,
            'occupation'       => $this->occupation,
            'salary'           => $this->salary,
            'family_group'     => $this->family_group,
            'family_members'   => FamilyMemberResource::collection($this->whenLoaded('familyMembers')),
            'created_at'       => $this->created_at,

            // relaciones opcionales
            'addresses'        => AddressResource::collection($this->whenLoaded('addresses')),
            'interactions'     => CrmInteractionResource::collection($this->whenLoaded('interactions')),
            'spouses'          => ClientResource::collection($this->whenLoaded('spouses')),
        ];

    }
}
