<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'fiscal_year' => 'sometimes|required|integer|min:2020|max:2050',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'total_amount' => 'sometimes|required|numeric|min:0',
            'budget_lines' => 'nullable|array',
            'budget_lines.*.account_id' => 'required_with:budget_lines|exists:chart_of_accounts,id',
            'budget_lines.*.description' => 'nullable|string|max:255',
            'budget_lines.*.budgeted_amount' => 'required_with:budget_lines|numeric|min:0',
            'budget_lines.*.quarter_1' => 'nullable|numeric|min:0',
            'budget_lines.*.quarter_2' => 'nullable|numeric|min:0',
            'budget_lines.*.quarter_3' => 'nullable|numeric|min:0',
            'budget_lines.*.quarter_4' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del presupuesto es obligatorio',
            'fiscal_year.required' => 'El año fiscal es obligatorio',
            'start_date.required' => 'La fecha de inicio es obligatoria',
            'end_date.required' => 'La fecha de fin es obligatoria',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'total_amount.required' => 'El monto total es obligatorio',
            'total_amount.min' => 'El monto total debe ser mayor a 0',
            'budget_lines.*.account_id.exists' => 'La cuenta contable seleccionada no existe',
            'budget_lines.*.budgeted_amount.min' => 'El monto presupuestado debe ser mayor a 0',
        ];
    }
}
