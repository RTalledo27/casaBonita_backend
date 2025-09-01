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
            'employee_type' => 'required|in:asesor_inmobiliario,vendedor,administrativo,gerente,jefe_ventas',
            'base_salary' => 'required|numeric|min:0|max:999999.99',
            'variable_salary' => 'nullable|numeric|min:0|max:999999.99',
            'commission_percentage' => 'nullable|numeric|min:0|max:100',
            'individual_goal' => 'nullable|numeric|min:0|max:9999999999.99',
            'is_commission_eligible' => 'boolean',
            'is_bonus_eligible' => 'boolean',
            'bank_account' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'bank_cci' => 'nullable|string|max:50',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:50',
            'team_id' => 'nullable|integer|exists:teams,team_id',
            'supervisor_id' => 'nullable|integer|exists:employees,employee_id',
            'hire_date' => 'required|date|before_or_equal:today',
            'termination_date' => 'nullable|date|after:hire_date',
            'employment_status' => 'required|in:activo,inactivo,de_vacaciones,licencia,terminado',
            'contract_type' => 'nullable|string|max:50',
            'work_schedule' => 'nullable|string|max:100',
            'social_security_number' => 'nullable|string|max:20',
            'afp_code' => 'nullable|string|max:10',
            'cuspp' => 'nullable|string|max:20',
            'health_insurance' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000'
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
            'employee_type.in' => 'El tipo de empleado debe ser: asesor_inmobiliario, vendedor, administrativo, gerente o jefe_ventas',
            'base_salary.required' => 'El salario base es obligatorio',
            'base_salary.min' => 'El salario base debe ser mayor a 0',
            'commission_percentage.max' => 'El porcentaje de comisión no puede ser mayor a 100%',
            'hire_date.required' => 'La fecha de contratación es obligatoria',
            'hire_date.before_or_equal' => 'La fecha de contratación no puede ser futura',
            'termination_date.after' => 'La fecha de terminación debe ser posterior a la fecha de contratación',
            'team_id.exists' => 'El equipo seleccionado no existe',
            'supervisor_id.exists' => 'El supervisor seleccionado no existe',
            'employment_status.required' => 'El estado de empleo es obligatorio',
            'employment_status.in' => 'El estado debe ser: activo, inactivo, de_vacaciones, licencia o terminado'
        ];
    }

    protected function prepareForValidation(): void
    {
        // Establecer valores por defecto para asesores
        if (in_array($this->employee_type, ['asesor_inmobiliario', 'vendedor'])) {
            $this->merge([
                'is_commission_eligible' => true,
                'is_bonus_eligible' => true
            ]);
            
            // Establecer porcentaje de comisión por defecto si no se proporciona
            if (!$this->commission_percentage) {
                $this->merge(['commission_percentage' => 5.0]); // 5% por defecto
            }
        }

        // Establecer estado por defecto
        if (!$this->employment_status) {
            $this->merge(['employment_status' => 'activo']);
        }
    }
}