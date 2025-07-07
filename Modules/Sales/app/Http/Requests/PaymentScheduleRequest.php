<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentScheduleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'contract_id' => 'required|exists:contracts,contract_id',
            'due_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|string|in:pendiente,pagado,vencido,anulado', // Ajusta los estados si es necesario 
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('sales.schedules.store') ?? false;
    }
}
