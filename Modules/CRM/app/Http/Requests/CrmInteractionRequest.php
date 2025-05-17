<?php

namespace Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrmInteractionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return[
        'client_id' => 'required|exists:clients,client_id',
        'channel' => 'required|in:call,email,whatsapp,visit,other',
        'notes' => 'nullable|string',
        'date' => 'required|date', // ← CAMBIADO
    ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
