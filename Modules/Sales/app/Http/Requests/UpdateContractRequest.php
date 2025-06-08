<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
{

        return [
        'sign_date'   => 'sometimes|date',
        'total_price' => 'sometimes|numeric',
        'currency'    => 'sometimes|string|size:3',
        'status'      => 'sometimes|in:vigente,resuelto,cancelado',
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
