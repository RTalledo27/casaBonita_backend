<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $contractId = $this->route('contract') ?? $this->route('id');
        
        return [
            'contract_number' => [
                'sometimes',
                'string',
                Rule::unique('contracts', 'contract_number')->ignore($contractId, 'contract_id')
            ],
            'reservation_id' => 'sometimes|exists:reservations,reservation_id',
            'advisor_id' => 'sometimes|exists:employees,employee_id',
            'total_price' => 'sometimes|numeric|min:0',
            'financing_amount' => 'nullable|numeric|min:0',
            'term_months' => 'nullable|integer|min:1|max:120',
            'monthly_payment' => 'nullable|numeric|min:0',
            'sign_date' => 'sometimes|date',
            'status' => 'sometimes|in:borrador,vigente,cancelado,finalizado',
            'currency' => 'sometimes|string|max:3',
            
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
        return $this->user()?->can('sales.contracts.update') ?? false;
    }
}