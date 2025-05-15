<?php
// Modules/Security/Http/Requests/UpdateUserRequest.php

namespace Modules\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo usuarios con permiso de actualización pueden hacerlo
        return $this->user()->can('security.users.update');
    }

    public function rules(): array
    {
        // Obtenemos el ID del usuario a actualizar desde el route-model binding
        $userId = $this->route('user')->user_id;

        return [
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:60',
                Rule::unique('users', 'username')->ignore($userId, 'user_id'),
            ],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:120',
                Rule::unique('users', 'email')->ignore($userId, 'user_id'),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'status'   => ['sometimes', 'required', Rule::in(['active', 'blocked'])],
            'roles'    => ['sometimes', 'array'],
            'roles.*'  => ['integer', 'exists:roles,role_id'],
        ];
    }
}
