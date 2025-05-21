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
            // ACCESS INFO
            'username'              => ['required', 'string', 'max:60', 'unique:users,username'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            // Laravel por convención busca password_confirmation
            'password_confirmation' => ['required', 'same:password'],

            'status'                => ['required', 'in:active,blocked'],

            // PERSONAL INFO
            'first_name'            => ['required', 'string', 'max:50'],
            'last_name'             => ['required', 'string', 'max:50'],
            'birth_date'            => ['required', 'date', 'before:today'],

            // CONTACT INFO
            'dni'                   => ['required', 'string', 'max:20'],
            'phone'                 => ['required', 'string', 'max:20'],
            'address'               => ['nullable', 'string', 'max:255'],

            // WORK INFO
            'position'              => ['required', 'string', 'max:60'],
            'department'            => ['required', 'string', 'max:60'],
            'hire_date'             => ['nullable', 'date', 'before_or_equal:today'],

            // PHOTO
            'photo_profile'         => ['nullable', 'image', 'max:2048'], // opcional, máximo 2 MB

            // ROLES
            'roles'                 => ['required', 'array'],
            // Según tu último feedback, el API espera nombres, no IDs
            'roles.*'               => ['string', 'exists:roles,name'], // array de IDs de roles
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

