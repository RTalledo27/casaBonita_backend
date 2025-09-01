<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reservation_date' => 'sometimes|date',
            'expiration_date'  => 'sometimes|date|after_or_equal:reservation_date',
            'deposit_amount'   => 'nullable|numeric',
            'status'           => 'sometimes|in:pendiente_pago,completada,cancelada,convertida',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('sales.reservations.update') ?? false;
    }
}
