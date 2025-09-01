<?php
// Modules/CRM/Http/Requests/UpdateClientRequest.php

namespace Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Verifica permiso de actualización
        return $this->user()->can('crm.clients.update');
    }

    public function rules(): array
    {
        // $this->route('client') devuelve el modelo Client por su {client} binding
        $clientId = $this->route('client')->client_id;

        return [
            'first_name'     => 'sometimes|required|string|max:80',
            'last_name'      => 'sometimes|required|string|max:80',
            'doc_type'       => ['sometimes', 'required', Rule::in(['DNI', 'CE', 'RUC', 'PAS'])],
            'doc_number'     => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('clients', 'doc_number')->ignore($clientId, 'client_id'),
            ],
            'email'          => 'nullable|email|max:120',
            'primary_phone'  => 'nullable|digits_between:6,15',
            'secondary_phone' => 'nullable|digits_between:6,15',
            'marital_status' => ['nullable', Rule::in(['soltero', 'casado', 'divorciado', 'viudo'])],
            'type'           => ['sometimes', 'required', Rule::in(['lead', 'client', 'provider'])],
            'date'           => 'nullable|date',
            'occupation'     => 'nullable|string',
            'salary'         => 'nullable|numeric',
            'family_group'   => 'nullable|string',
            'family_members'        => 'nullable|array',
            'family_members.*.first_name' => 'required_with:family_members|string|max:80',
            'family_members.*.last_name'  => 'required_with:family_members|string|max:80',
            'family_members.*.dni'        => 'required_with:family_members|string|max:20',
            'family_members.*.relation'   => 'required_with:family_members|string|max:60',  ];
    }
}
