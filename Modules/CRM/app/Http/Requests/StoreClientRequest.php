<?php
// Modules/CRM/Http/Requests/StoreClientRequest.php

namespace Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Verifica permiso de creación
        return $this->user()->can('crm.clients.create');
    }

    public function rules(): array
    {
        return [
            'first_name'       => 'required|string|max:80',
            'last_name'        => 'required|string|max:80',
            'doc_type'         => 'required|in:DNI,CE,RUC,PAS',
            'doc_number'       => 'required|string|max:20|unique:clients,doc_number',
            'email'            => 'nullable|email|max:120',
            'primary_phone'    => 'nullable|digits_between:6,15',
            'secondary_phone'  => 'nullable|digits_between:6,15',
            'marital_status'   => 'nullable|in:soltero,casado,divorciado,viudo',
            'type'             => 'required|in:lead,client,provider',
            'date'             => 'nullable|date',
            'occupation'       => 'nullable|string|max:255',
            'salary'           => 'nullable|numeric|min:0',
            'family_group'     => 'nullable|string|max:255',
            'spouse_id'        => 'nullable|exists:clients,client_id',
            'family_members'        => 'nullable|array',
            'family_members.*.first_name' => 'required_with:family_members|string|max:80',
            'family_members.*.last_name'  => 'required_with:family_members|string|max:80',
            'family_members.*.dni'        => 'required_with:family_members|string|max:20',
            'family_members.*.relation'   => 'required_with:family_members|string|max:60',
            'addresses'         => 'nullable|array',
            'addresses.*.line1' => 'required_with:addresses|string',
        ];
    }
  

}
