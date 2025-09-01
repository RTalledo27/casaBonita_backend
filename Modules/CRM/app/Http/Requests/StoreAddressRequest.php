<?php

namespace Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.addresses.create');
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|exists:clients,client_id',
            'line1'     => 'required|string|max:120',
            'line2'     => 'nullable|string|max:120',
            'city'      => 'required|string|max:60',
            'state'     => 'nullable|string|max:60',
            'country'   => 'required|string|max:60',
            'zip_code'  => 'nullable|string|max:15',
        ];
    }
}
