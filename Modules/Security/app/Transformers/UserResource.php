<?php

namespace Modules\Security\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->user_id,
            'username'     => $this->username,
            'first_name'    => $this->first_name,
            'last_name'    => $this->last_name,
            'dni'          => $this->dni,
            'email'        => $this->email,
            'name'          => "{$this->first_name} {$this->last_name}",

            'phone'        => $this->phone,
            'position'     => $this->position,
            'department'   => $this->department,
            'address'      => $this->address,
            'hire_date'    => $this->hire_date,
            'birth_date'   => $this->birth_date,
            'status'       => $this->status,
            'photo_url'     => $this->photo_profile
                ? Storage::url($this->photo_profile)
                :null,
            'cv_url'        => $this->cv_file
                ? Storage::url($this->cv_file)
                : null,
            'created_by'   => $this->created_by,

            // Password management fields
            'must_change_password' => $this->must_change_password ?? false,
            'password_changed_at' => $this->password_changed_at,
            'last_login_at' => $this->last_login_at,

            'roles'        => $this->roles->pluck('name')->toArray(),
            'permissions'    => $this->getAllPermissions()                              // Spatie permisos
                ->pluck('name')
                ->toArray(),
        ];
    }
}
