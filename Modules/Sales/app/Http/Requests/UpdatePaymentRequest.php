<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'journal_entry_id' => 'nullable|exists:journal_entries,journal_entry_id',
            'payment_date'     => 'sometimes|date',
            'amount'           => 'sometimes|numeric',
            'method'           => 'sometimes|in:transferencia,efectivo,tarjeta',
            'reference'        => 'nullable|string|max:60',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('sales.payments.update') ?? false;
    }
}
