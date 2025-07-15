<?php

namespace Modules\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
   
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee');
        
        return [
            'user_id' => [
                'sometimes',
                'integer',
                'exists:users,user_id',
                Rule::unique('employees', 'user_id')->ignore($employeeId, 'employee_id')
            ],
            'employee_code' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('employees', 'employee_code')->ignore($employeeId, 'employee_id')
            ],
            'employee_type' => 'sometimes|in:advisor,manager,admin,hr',
            'position' => 'sometimes|string|max:100',
            'department' => 'sometimes|string|max:100',
            'hire_date' => 'sometimes|date|before_or_equal:today',
            'base_salary' => 'sometimes|numeric|min:0|max:999999.99',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'individual_goal' => 'nullable|numeric|min:0|max:9999999999.99',
            'is_advisor' => 'sometimes|boolean',
            'team_id' => 'nullable|integer|exists:teams,team_id',
            'status' => 'sometimes|in:active,inactive,suspended'
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'El usuario seleccionado no existe',
            'user_id.unique' => 'Este usuario ya tiene un registro de empleado',
            'employee_code.unique' => 'El código de empleado ya está en uso',
            'employee_type.in' => 'El tipo de empleado debe ser: advisor, manager, admin o hr',
            'hire_date.before_or_equal' => 'La fecha de contratación no puede ser futura',
            'base_salary.min' => 'El salario base debe ser mayor a 0',
            'commission_rate.max' => 'La tasa de comisión no puede ser mayor a 100%',
            'team_id.exists' => 'El equipo seleccionado no existe',
            'status.in' => 'El estado debe ser: active, inactive o suspended'
        ];
    }

    protected function prepareForValidation(): void
    {
        // Actualizar is_advisor basado en employee_type si se proporciona
        if ($this->has('employee_type')) {
            $this->merge(['is_advisor' => $this->employee_type === 'advisor']);
        }
    }
}
