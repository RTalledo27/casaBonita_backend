<?php

namespace Modules\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'status'   => ['in:active,blocked'],
            'roles'    => ['array', 'exists:roles,role_id'], // array de IDs de roles
        ];    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()!== null;
    }

    public function messages(): array
    {
        return [
            'roles.exists' => 'Uno o más roles seleccionados no existen.',
        ];
    }
}

