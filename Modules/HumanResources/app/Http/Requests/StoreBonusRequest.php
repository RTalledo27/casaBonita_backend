<?php

namespace Modules\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|integer|exists:employees,employee_id',
            'bonus_type_id' => 'required|integer|exists:bonus_types,bonus_type_id',
            'bonus_goal_id' => 'nullable|integer|exists:bonus_goals,bonus_goal_id',
            'bonus_amount' => 'required|numeric|min:0|max:999999.99',
            'target_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'achieved_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'achievement_percentage' => 'nullable|numeric|min:0|max:1000',
            'period_month' => 'nullable|integer|min:1|max:12',
            'period_year' => 'nullable|integer|min:2020|max:2030',
            'period_quarter' => 'nullable|integer|min:1|max:4',
            'notes' => 'nullable|string|max:1000',
            'requires_approval' => 'boolean',
            'auto_approve' => 'boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'El empleado es obligatorio',
            'employee_id.exists' => 'El empleado seleccionado no existe',
            'bonus_type_id.required' => 'El tipo de bono es obligatorio',
            'bonus_type_id.exists' => 'El tipo de bono seleccionado no existe',
            'bonus_goal_id.exists' => 'La meta de bono seleccionada no existe',
            'bonus_amount.required' => 'El monto del bono es obligatorio',
            'bonus_amount.min' => 'El monto debe ser mayor a 0',
            'bonus_amount.max' => 'El monto no puede exceder S/ 999,999.99',
            'target_amount.max' => 'El monto objetivo no puede exceder S/ 999,999,999.99',
            'achieved_amount.max' => 'El monto alcanzado no puede exceder S/ 999,999,999.99',
            'achievement_percentage.min' => 'El porcentaje de logro debe ser mayor a 0',
            'achievement_percentage.max' => 'El porcentaje de logro no puede exceder 1000%',
            'period_month.min' => 'El mes debe estar entre 1 y 12',
            'period_month.max' => 'El mes debe estar entre 1 y 12',
            'period_year.min' => 'El año debe ser mayor a 2020',
            'period_year.max' => 'El año no puede ser mayor a 2030',
            'period_quarter.min' => 'El trimestre debe estar entre 1 y 4',
            'period_quarter.max' => 'El trimestre debe estar entre 1 y 4',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres'
        ];
    }

    protected function prepareForValidation(): void
    {
        // Establecer período actual si no se proporciona
        if (!$this->period_month) {
            $this->merge(['period_month' => now()->month]);
        }

        if (!$this->period_year) {
            $this->merge(['period_year' => now()->year]);
        }

        // Calcular porcentaje de logro si se proporcionan montos
        if ($this->target_amount && $this->achieved_amount && !$this->achievement_percentage) {
            $percentage = ($this->achieved_amount / $this->target_amount) * 100;
            $this->merge(['achievement_percentage' => round($percentage, 2)]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que el empleado esté activo
            if ($this->employee_id) {
                $employee = \Modules\HumanResources\Models\Employee::find($this->employee_id);
                if ($employee && $employee->employment_status !== 'activo') {
                    $validator->errors()->add('employee_id', 'El empleado debe estar activo para recibir bonos');
                }
            }

            // Validar que no exista un bono duplicado para el mismo período
            if ($this->employee_id && $this->bonus_type_id && $this->period_month && $this->period_year) {
                $exists = \Modules\HumanResources\Models\Bonus::where('employee_id', $this->employee_id)
                    ->where('bonus_type_id', $this->bonus_type_id)
                    ->where('period_month', $this->period_month)
                    ->where('period_year', $this->period_year)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('bonus_type_id', 'Ya existe un bono de este tipo para el empleado en este período');
                }
            }
        });
    }
}
