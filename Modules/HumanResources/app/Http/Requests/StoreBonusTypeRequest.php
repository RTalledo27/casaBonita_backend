<?php

namespace Modules\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBonusTypeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'type_code' => 'required|string|unique:bonus_types,type_code',
            'type_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'calculation_method' => 'required|string',
            'is_automatic' => 'required|boolean',
            'requires_approval' => 'required|boolean',
            'applicable_employee_types' => 'nullable|array',
            'frequency' => 'required|string',
            'is_active' => 'required|boolean'
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
