<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReservationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'lot_id'           => 'required|exists:lots,lot_id',
            'client_id'        => 'required|exists:clients,client_id',
            'reservation_date' => 'required|date',
            'deposit_amount'   => 'nullable|numeric|min:0',

            'expiration_date' => 'required|date|after_or_equal:reservation_date',
            'deposit_amount' => 'required|numeric|min:0',
            'deposit_method' => 'nullable|string|max:255',
            'deposit_reference' => 'nullable|string|max:255',
            'deposit_paid_at' => 'nullable|date',
            'status' => 'required|string|in:pendiente,confirmada,cancelada,convertida', // 'convertida' añadido

        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('sales.reservations.store') ?? false;
    }
}
