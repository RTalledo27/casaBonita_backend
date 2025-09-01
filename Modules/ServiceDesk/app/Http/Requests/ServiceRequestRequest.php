<?php

namespace Modules\ServiceDesk\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceRequestRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'contract_id'    => 'nullable|exists:contracts,contract_id',
            'ticket_type'    => 'required|in:garantia,mantenimiento,otro',
            'priority'       => 'required|in:baja,media,alta,critica',
            'status'         => 'sometimes|in:abierto,en_proceso,cerrado',
            'description'    => 'nullable|string|max:3000',
            'opened_at'      => 'nullable|date',
            'sla_due_at'     => 'nullable|date',
            'escalated_at'   => 'nullable|date',

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
