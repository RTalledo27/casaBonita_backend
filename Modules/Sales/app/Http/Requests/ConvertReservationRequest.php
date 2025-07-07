<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConvertReservationRequest extends FormRequest
{
  
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_number' => 'required|string|max:255|unique:contracts,contract_number',
            'sign_date' => 'required|date',
            'total_price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:3',

            'approvers'       => 'sometimes|array',
            'approvers.*'     => 'integer|exists:users,user_id',
        ];
    }
}
