<?php

namespace Modules\ServiceDesk\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceActionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'action_type'       => 'required|in:comentario,cambio_estado,escalado',
            'notes'             => 'nullable|string|max:3000',
            'next_action_date'  => 'nullable|date',
            'performed_at'      => 'nullable|date',
            'ticket_id'         => 'required|exists:service_requests,ticket_id',
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
