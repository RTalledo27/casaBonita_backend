<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'method'           => 'required|in:transferencia,efectivo,tarjeta',
            'reference'        => 'nullable|string|max:60',

            'schedule_id' => 'required|exists:payment_schedules,schedule_id',
            'journal_entry_id' => 'nullable|exists:journal_entries,journal_entry_id', // Puede ser nulo si se crea después
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'reference' => 'nullable|string|max:255',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('sales.payments.store') ?? false;
    }
}
