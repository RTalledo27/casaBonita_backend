<?php
// Modules/Security/Http/Requests/UpdateUserRequest.php

namespace Modules\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            // ACCESS
            // ACCESS INFO (sólo if cambian)
            'username'              => ['sometimes', 'required', 'string', 'alpha_num', 'min:3', 'max:60', Rule::unique('users', 'username')->ignore($userId, 'user_id')],
            'email'                 => ['sometimes', 'required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($userId, 'user_id')],
            'password'              => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'password_confirmation' => ['required_with:password', 'same:password'],

            'status'                => ['sometimes', 'required', Rule::in(['active', 'blocked'])],

            // PERSONAL INFO
            'first_name'            => ['sometimes', 'required', 'string', 'max:50'],
            'last_name'             => ['sometimes', 'required', 'string', 'max:50'],
            'birth_date'            => ['sometimes', 'required', 'date', 'before:today'],

            // CONTACT INFO
            'dni' => [
                'sometimes',
                'required',
                'digits_between:6,20',
                Rule::unique('users', 'dni')->ignore($userId, 'user_id'),
            ],            'phone'                 => ['sometimes', 'required', 'regex:/^[0-9\-\+\s]+$/', 'min:7', 'max:20'],
            'address'               => ['sometimes', 'nullable', 'string', 'max:255'],

            // WORK INFO
            'position'              => ['sometimes', 'required', 'string', 'max:60'],
            'department'            => ['sometimes', 'required', 'string', 'max:60'],
            'hire_date'             => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],

            // PHOTO
            'photo_profile'         => ['sometimes', 'nullable', 'image', 'max:2048'],

            // ROLES
            'roles'                 => ['sometimes', 'required', 'array', 'min:1'],
            'roles.*'               => ['string', 'exists:roles,name'],
        ];
    }

    /**
     * Custom validation messages.
     *
     * 
     * @return array
     */
    public function messages(): array
    {
        return [
            'username.alpha_num'          => 'El nombre de usuario sólo puede contener letras y números.',
            'username.min'                => 'El nombre de usuario debe tener al menos 3 caracteres.',
            'email.email'                 => 'El correo no tiene un formato válido.',
            'password.confirmed'          => 'Las contraseñas no coinciden.',
            'status.in'                   => 'El estado debe ser “active” o “blocked”.',
            'dni.digits_between'          => 'El DNI debe tener entre 6 y 20 dígitos.',
            'phone.regex'                 => 'El teléfono sólo puede contener dígitos, espacios, + o -.',
            'birth_date.before'           => 'La fecha de nacimiento debe ser anterior a hoy.',
            'hire_date.before_or_equal'   => 'La fecha de ingreso no puede ser futura.',
            'photo_profile.image'         => 'El archivo debe ser una imagen.',
            'photo_profile.max'           => 'La imagen no puede superar los 2 MB.',
            'roles.required'              => 'Debes asignar al menos un rol.',
            'roles.*.exists'              => 'El rol seleccionado no existe.',
            'dni.unique' => 'El DNI ya se encuentra registrado.'
        ];
    }
}
