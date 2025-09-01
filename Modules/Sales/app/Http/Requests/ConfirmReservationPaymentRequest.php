<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmReservationPaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'deposit_method' => 'required|string|max:255',
            'deposit_reference' => 'nullable|string|max:255',
            // Puedes añadir más validaciones si es necesario, por ejemplo, para el monto
            // 'amount_paid' => 'required|numeric|min:0',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('sales.reservations.update') ?? false;
    }
}
