<?php

namespace Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmInteractionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'date'    => ['sometimes', 'required', 'date'],
            'channel' => ['sometimes', 'required', Rule::in(['call', 'email', 'whatsapp', 'visit', 'other'])],
            'notes'   => 'nullable|string',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('crm.interactions.update');
    }
}
