<?php

namespace Modules\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBonusTypeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $bonusTypeId = $this->route('bonus_type');

        return [
            'type_code' => [
                'sometimes',
                'string',
                Rule::unique('bonus_types', 'type_code')->ignore($bonusTypeId, 'bonus_type_id'),
            ],
            'type_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'calculation_method' => 'sometimes|string',
            'is_automatic' => 'sometimes|boolean',
            'requires_approval' => 'sometimes|boolean',
            'applicable_employee_types' => 'nullable|array',
            'frequency' => 'sometimes|string',
            'is_active' => 'sometimes|boolean'
        ];
        }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
