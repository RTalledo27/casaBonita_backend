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
            'ticket_type'    => 'required|in:incidente,solicitud,cambio,garantia,mantenimiento,otro',
            'priority'       => 'required|in:baja,media,alta,critica',
            'status'         => 'sometimes|in:abierto,en_progreso,pendiente,escalado,resuelto,reabierto,cerrado',
            'description'    => 'nullable|string|max:3000',
            'opened_at'      => 'nullable|date',
            'sla_due_at'     => 'nullable|date',
            'escalated_at'   => 'nullable|date',
            'category_id'    => 'nullable|exists:service_categories,id',

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
