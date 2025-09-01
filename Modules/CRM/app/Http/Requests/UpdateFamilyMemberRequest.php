<?php

namespace Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFamilyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.clients.update');
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:80'],
            'last_name'  => ['sometimes', 'required', 'string', 'max:80'],
            'dni'        => ['sometimes', 'required', 'string', 'max:20'],
            'relation'   => ['sometimes', 'required', 'string', 'max:60'],
        ];
    }
}
