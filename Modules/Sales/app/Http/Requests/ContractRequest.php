<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContractRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'reservation_id'  => 'required|exists:reservations,reservation_id',
            'contract_number' => 'required|string|unique:contracts,contract_number',
            'sign_date'       => 'required|date',
            'total_price'     => 'required|numeric',
            'currency'        => 'required|string|size:3',
            'status'          => 'required|in:vigente,resuelto,cancelado',
            'schedules'       => 'nullable|array',
            'schedules.*.due_date' => 'required_with:schedules|date',
            'schedules.*.amount'   => 'required_with:schedules|numeric',
        ];
        }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('sales.contracts.store
        ') ?? false;
    }
}
