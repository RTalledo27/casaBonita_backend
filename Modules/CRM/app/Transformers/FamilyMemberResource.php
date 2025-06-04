<?php

namespace Modules\CRM\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FamilyMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'family_member_id' => $this->family_member_id,
            'client_id'       => $this->client_id,
            'first_name'      => $this->first_name,
            'last_name'       => $this->last_name,
            'dni'             => $this->dni,
            'relation'        => $this->relation,
        ];
    }
}
