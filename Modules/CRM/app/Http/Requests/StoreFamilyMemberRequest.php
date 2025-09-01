<?php

namespace Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFamilyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.clients.create');
    }

    public function rules(): array
    {
        return [
            'client_id'  => 'required|exists:clients,client_id',
            'first_name' => 'required|string|max:80',
            'last_name'  => 'required|string|max:80',
            'dni'        => 'required|string|max:20',
            'relation'   => 'required|string|max:60',
        ];
    }
}
