<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {

        $contractId = $this->route('contract') ? $this->route('contract')->contract_id : null;

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
            'status' => 'required|string|in:vigente,pendiente,anulado,reemplazado',
            'transferred_amount_from_previous_contract' => 'nullable|numeric|min:0',
            
            'schedules'           => 'nullable|array',
            'schedules.*.due_date' => 'required_with:schedules|date',
            'schedules.*.amount'   => 'required_with:schedules|numeric',
            'approvers'           => 'sometimes|array',
            'approvers.*'         => 'integer|exists:users,user_id',
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
