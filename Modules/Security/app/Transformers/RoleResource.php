<?php

namespace Modules\Security\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'role_id' => $this->role_id,
            'name' => $this->name,
            'description' => $this->description,
            'permissions' => $this->permissions,
            'guard_name' => $this->guard_name,
            'created_at' => $this->created_at,
        ];
    }
}
