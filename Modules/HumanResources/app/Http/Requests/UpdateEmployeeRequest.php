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
            'employee_type' => 'sometimes|in:asesor_inmobiliario,vendedor,administrativo,gerente,jefe_ventas',
            'position' => 'sometimes|string|max:100',
            'department' => 'sometimes|string|max:100',
            'hire_date' => 'sometimes|date|before_or_equal:today',
            'termination_date' => 'nullable|date|after:hire_date',
            'base_salary' => 'sometimes|numeric|min:0|max:999999.99',
            'variable_salary' => 'nullable|numeric|min:0|max:999999.99',
            'commission_percentage' => 'nullable|numeric|min:0|max:100',
            'individual_goal' => 'nullable|numeric|min:0|max:9999999999.99',
            'is_commission_eligible' => 'sometimes|boolean',
            'is_bonus_eligible' => 'sometimes|boolean',
            'bank_name' => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'team_id' => 'nullable|integer|exists:teams,team_id',
            'supervisor_id' => 'nullable|integer|exists:employees,employee_id',
            'employment_status' => 'sometimes|in:activo,inactivo,suspendido,terminado',
            'contract_type' => 'nullable|in:tiempo_completo,medio_tiempo,contrato,freelance',
            'work_schedule' => 'nullable|string|max:100',
            'social_security_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string'
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'El usuario seleccionado no existe',
            'user_id.unique' => 'Este usuario ya tiene un registro de empleado',
            'employee_code.unique' => 'El código de empleado ya está en uso',
            'employee_type.in' => 'El tipo de empleado debe ser: asesor_inmobiliario, vendedor, administrativo, gerente o jefe_ventas',
            'hire_date.before_or_equal' => 'La fecha de contratación no puede ser futura',
            'termination_date.after' => 'La fecha de terminación debe ser posterior a la fecha de contratación',
            'base_salary.min' => 'El salario base debe ser mayor a 0',
            'commission_percentage.max' => 'El porcentaje de comisión no puede ser mayor a 100%',
            'team_id.exists' => 'El equipo seleccionado no existe',
            'supervisor_id.exists' => 'El supervisor seleccionado no existe',
            'employment_status.in' => 'El estado de empleo debe ser: activo, inactivo, suspendido o terminado',
            'contract_type.in' => 'El tipo de contrato debe ser: tiempo_completo, medio_tiempo, contrato o freelance'
        ];
    }

    protected function prepareForValidation(): void
    {
        // Actualizar eligibilidad de comisiones y bonos basado en employee_type si se proporciona
        if ($this->has('employee_type')) {
            $eligibleTypes = ['asesor_inmobiliario', 'vendedor'];
            $isEligible = in_array($this->employee_type, $eligibleTypes);
            
            $this->merge([
                'is_commission_eligible' => $isEligible,
                'is_bonus_eligible' => $isEligible
            ]);
        }
    }
}
