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
            'expiration_date'  => 'required|date|after_or_equal:reservation_date',
            'deposit_amount'   => 'nullable|numeric',
            'status'           => 'required|in:pendiente_pago,completada,cancelada,convertida',
        ];    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('sales.reservations.store') ?? false;
    }
}
