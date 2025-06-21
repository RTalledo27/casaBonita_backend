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
            'deposit_method'   => 'required|in:transferencia,efectivo,tarjeta,yape,plin,otro',
            'deposit_reference' => 'nullable|string|max:60',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('sales.reservations.update') ?? false;
    }
}
