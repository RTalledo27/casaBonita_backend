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
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('sales.schedules.update') ?? false;
    }
}
