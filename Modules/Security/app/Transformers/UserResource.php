<?php

namespace Modules\Security\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->user_id,
            'username' => $this->username,
            'email'    => $this->email,
            'status'   => $this->status,
            'roles'    => $this->whenLoaded('roles')->pluck('name'),
        ];    }
}
