<?php

namespace Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'line1'    => ['sometimes', 'required', 'string', 'max:120'],
            'line2'    => ['nullable', 'string', 'max:120'],
            'city'     => ['sometimes', 'required', 'string', 'max:60'],
            'state'    => ['nullable', 'string', 'max:60'],
            'country'  => ['sometimes', 'required', 'string', 'max:60'],
            'zip_code' => ['nullable', 'string', 'max:15'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('crm.addresses.update');
    }
}
