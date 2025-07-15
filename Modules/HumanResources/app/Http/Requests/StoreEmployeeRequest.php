<?php

namespace Modules\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,user_id|unique:employees,user_id',
            'employee_code' => 'nullable|string|max:20|unique:employees,employee_code',
            'employee_type' => 'required|in:advisor,manager,admin,hr',
            'position' => 'required|string|max:100',
            'department' => 'required|string|max:100',
            'hire_date' => 'required|date|before_or_equal:today',
            'base_salary' => 'required|numeric|min:0|max:999999.99',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'individual_goal' => 'nullable|numeric|min:0|max:9999999999.99',
            'is_advisor' => 'boolean',
            'team_id' => 'nullable|integer|exists:teams,team_id',
            'status' => 'required|in:active,inactive,suspended'
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'El usuario es obligatorio',
            'user_id.exists' => 'El usuario seleccionado no existe',
            'user_id.unique' => 'Este usuario ya tiene un registro de empleado',
            'employee_code.unique' => 'El código de empleado ya está en uso',
            'employee_type.required' => 'El tipo de empleado es obligatorio',
            'employee_type.in' => 'El tipo de empleado debe ser: advisor, manager, admin o hr',
            'position.required' => 'El cargo es obligatorio',
            'department.required' => 'El departamento es obligatorio',
            'hire_date.required' => 'La fecha de contratación es obligatoria',
            'hire_date.before_or_equal' => 'La fecha de contratación no puede ser futura',
            'base_salary.required' => 'El salario base es obligatorio',
            'base_salary.min' => 'El salario base debe ser mayor a 0',
            'commission_rate.max' => 'La tasa de comisión no puede ser mayor a 100%',
            'team_id.exists' => 'El equipo seleccionado no existe',
            'status.in' => 'El estado debe ser: active, inactive o suspended'
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convertir is_advisor basado en employee_type
        if ($this->employee_type === 'advisor') {
            $this->merge(['is_advisor' => true]);
        }

        // Establecer commission_rate por defecto para asesores
        if ($this->employee_type === 'advisor' && !$this->commission_rate) {
            $this->merge(['commission_rate' => 5.0]); // 5% por defecto
        }
    }
}