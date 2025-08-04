<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'contract_number' => 'required|string|unique:contracts,contract_number',
            'reservation_id' => 'required|exists:reservations,reservation_id',
            'advisor_id' => 'required|exists:employees,employee_id',
            'total_price' => 'required|numeric|min:0',
            'financing_amount' => 'nullable|numeric|min:0',
            'term_months' => 'nullable|integer|min:1|max:120',
            'monthly_payment' => 'nullable|numeric|min:0',
            'sign_date' => 'required|date',
            'status' => 'required|in:borrador,vigente,cancelado,finalizado',
            'currency' => 'required|string|max:3',
            
            // Financial fields migrated from Lot
            'funding' => 'nullable|numeric|min:0',
            'bpp' => 'nullable|numeric|min:0',
            'bfh' => 'nullable|numeric|min:0',
            'initial_quota' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('sales.contracts.store') ?? false;
    }
}