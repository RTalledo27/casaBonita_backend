<?php

namespace Modules\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'username'              => ['required', 'string', 'alpha_num', 'min:3', 'max:60', 'unique:users,username'],
            'email'                 => ['required', 'email', 'max:120', 'unique:users,email'],
            'password'              => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'password_confirmation' => ['required', 'same:password'],

            'status'                => ['required', Rule::in(['active', 'blocked'])],

            // PERSONAL INFO
            'first_name'            => ['required', 'string', 'max:50'],
            'last_name'             => ['required', 'string', 'max:50'],
            'birth_date'            => ['required', 'date', 'before:today'],

            // CONTACT INFO
            'dni' => ['required', 'digits_between:6,20', 'unique:users,dni'],
            'phone'                 => ['required', 'regex:/^[0-9\-\+\s]+$/', 'min:7', 'max:20'],
            'address'               => ['nullable', 'string', 'max:255'],

            // WORK INFO
            'position'              => ['required', 'string', 'max:60'],
            'department'            => ['required', 'string', 'max:60'],
            'hire_date'             => ['nullable', 'date', 'before_or_equal:today'],

            // PHOTO
            'photo_profile'         => ['nullable', 'image', 'max:2048'], // 2 MB

            // ROLES
            'roles'                 => ['required', 'array', 'min:1'],
            'roles.*'               => ['string', 'exists:roles,name'],
            
        ];  
      }

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
            'email.unique'                => 'El correo ya esta en uso.',
            'username.unique'             => 'El nombre de usuario ya está en uso.',
            'roles.*.exists'              => 'El rol seleccionado no existe.',
            'dni.unique' => 'El DNI ya se encuentra registrado.'
        ];
    }
}

