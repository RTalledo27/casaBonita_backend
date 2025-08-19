<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;


class UpdatePaymentScheduleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'due_date' => 'sometimes|date',
            'amount'   => 'sometimes|numeric',
            'status'   => 'sometimes|in:pendiente,pagado,vencido',
            'contract_id' => 'sometimes|required|exists:contracts,contract_id',
            
            // Campos adicionales para cuando se marca como pagado
            'payment_date' => 'sometimes|date',
            'amount_paid' => 'sometimes|numeric|min:0.01',
            'payment_method' => 'sometimes|in:cash,transfer,check,card',
            'notes' => 'sometimes|string|max:500',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('sales.schedules.update') ?? false;
    }
}
