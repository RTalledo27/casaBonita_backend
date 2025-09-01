<?php

namespace Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCrmInteractionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'client_id' => 'required|exists:clients,client_id',
            'user_id'   => 'required|exists:users,user_id',
            'date'      => 'required|date',
            'channel'   => 'required|in:call,email,whatsapp,visit,other',
            'notes'     => 'nullable|string',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('crm.interactions.create');
    }
}
