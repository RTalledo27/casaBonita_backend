<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado para hacer esta petición
     */
    public function authorize(): bool
    {
        // Verificar que el usuario tenga permisos para crear presupuestos
        return true;
    }

    /**
     * Reglas de validación para los datos de entrada
     */
    public function rules(): array
    {
        return [
            // Validaciones para el presupuesto principal
            'name' => [
                'required',
                'string',
                'max:255',
                // Validar que el nombre sea único para el año fiscal
                Rule::unique('budgets')->where(function ($query) {
                    return $query->where('fiscal_year', $this->fiscal_year)
                        ->whereNull('deleted_at');
                })
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'fiscal_year' => [
                'required',
                'integer',
                'min:2020',
                'max:2050'
            ],
            'start_date' => [
                'required',
                'date',
                'after_or_equal:today'
            ],
            'end_date' => [
                'required',
                'date',
                'after:start_date'
            ],
            'total_amount' => [
                'required',
                'numeric',
                'min:0'
            ],
            'budget_lines' => [
                'nullable',
                'array'
            ],
            'budget_lines.*.account_id' => [
                'required_with:budget_lines',
                'exists:chart_of_accounts,id'
            ],
            'budget_lines.*.description' => [
                'nullable',
                'string',
                'max:255'
            ],
            'budget_lines.*.budgeted_amount' => [
                'required_with:budget_lines',
                'numeric',
                'min:0'
            ],
            'budget_lines.*.quarter_1' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'budget_lines.*.quarter_2' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'budget_lines.*.quarter_3' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'budget_lines.*.quarter_4' => [
                'nullable',
                'numeric',
                'min:0'
            ],
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            // Mensajes para el presupuesto principal
            'name.required' => 'El nombre del presupuesto es obligatorio',
            'name.unique' => 'Ya existe un presupuesto con este nombre para el año fiscal seleccionado',
            'fiscal_year.required' => 'El año fiscal es obligatorio',
            'fiscal_year.min' => 'El año fiscal debe ser mayor o igual a 2020',
            'fiscal_year.max' => 'El año fiscal debe ser menor o igual a 2050',
            'start_date.required' => 'La fecha de inicio es obligatoria',
            'start_date.after_or_equal' => 'La fecha de inicio debe ser igual o posterior a hoy',
            'end_date.required' => 'La fecha de fin es obligatoria',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'total_amount.required' => 'El monto total es obligatorio',
            'total_amount.min' => 'El monto total debe ser mayor a 0',

            // Mensajes para las líneas
            'budget_lines.*.account_id.exists' => 'La cuenta contable seleccionada no existe',
            'budget_lines.*.budgeted_amount.min' => 'El monto presupuestado debe ser mayor a 0',
        ];
    }

    /**
     * Validaciones adicionales después de las reglas básicas
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que no haya cuentas duplicadas en las líneas
            if ($this->budget_lines) {
                $accountIds = collect($this->budget_lines)->pluck('account_id')->toArray();
                if (count($accountIds) !== count(array_unique($accountIds))) {
                    $validator->errors()->add('budget_lines', 'No puede incluir la misma cuenta contable en múltiples líneas');
                }
            }

            // Validar que el año fiscal coincida con las fechas
            if ($this->start_date && $this->fiscal_year) {
                $startYear = date('Y', strtotime($this->start_date));
                $endYear = date('Y', strtotime($this->end_date));

                if ($this->fiscal_year != $startYear && $this->fiscal_year != $endYear) {
                    $validator->errors()->add('fiscal_year', 'El año fiscal debe coincidir con el año de las fechas de inicio o fin');
                }
            }

            // Validar que las cuentas seleccionadas estén activas
            if ($this->budget_lines) {
                $accountIds = collect($this->budget_lines)->pluck('account_id');
                $inactiveAccounts = \Modules\Accounting\Models\ChartOfAccount::whereIn('account_id', $accountIds)
                    ->where('is_active', false)
                    ->count();

                if ($inactiveAccounts > 0) {
                    $validator->errors()->add('budget_lines', 'Una o más cuentas contables seleccionadas están inactivas');
                }
            }
        });
    }

    /**
     * Preparar datos para validación
     */
    protected function prepareForValidation(): void
    {
        // Convertir strings vacíos a null para campos opcionales
        if ($this->description === '') {
            $this->merge(['description' => null]);
        }

        // Limpiar y formatear líneas
        if ($this->budget_lines) {
            $cleanLines = [];
            foreach ($this->budget_lines as $line) {
                // Remover líneas vacías o inválidas
                if (isset($line['account_id']) && isset($line['budgeted_amount'])) {
                    $cleanLines[] = [
                        'account_id' => $line['account_id'],
                        'description' => $line['description'] ?? null,
                        'budgeted_amount' => floatval($line['budgeted_amount']),
                        'quarter_1' => $line['quarter_1'] ?? null,
                        'quarter_2' => $line['quarter_2'] ?? null,
                        'quarter_3' => $line['quarter_3'] ?? null,
                        'quarter_4' => $line['quarter_4'] ?? null,
                    ];
                }
            }
            $this->merge(['budget_lines' => $cleanLines]);
        }
    }

    /**
     * Atributos personalizados para los mensajes de error
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre del presupuesto',
            'fiscal_year' => 'año fiscal',
            'start_date' => 'fecha de inicio',
            'end_date' => 'fecha de fin',
            'total_amount' => 'monto total',
            'budget_lines' => 'líneas del presupuesto',
            'budget_lines.*.account_id' => 'cuenta contable',
            'budget_lines.*.budgeted_amount' => 'monto presupuestado',
            'budget_lines.*.description' => 'descripción',
            'budget_lines.*.quarter_1' => 'primer trimestre',
            'budget_lines.*.quarter_2' => 'segundo trimestre',
            'budget_lines.*.quarter_3' => 'tercer trimestre',
            'budget_lines.*.quarter_4' => 'cuarto trimestre',
        ];
    }
}
