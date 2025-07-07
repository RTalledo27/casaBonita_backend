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

        $contractId = $this->route('contract')->contract_id;


        return [
            'reservation_id' => 'nullable|exists:reservations,reservation_id',
            'previous_contract_id' => 'nullable|exists:contracts,contract_id',
            'contract_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('contracts', 'contract_number')->ignore($contractId, 'contract_id'),
            ],
            'sign_date' => 'required|date',
            'total_price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:3',
            'status' => 'required|string|in:vigente,pendiente_aprobacion,anulado,reemplazado',
            'transferred_amount_from_previous_contract' => 'nullable|numeric|min:0',


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
