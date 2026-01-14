<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManzanaFinancingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'manzana_id' => ['required', 'integer', 'exists:manzanas,manzana_id'],
            'financing_type' => ['required', 'string', 'in:cash_only,installments,mixed'],
            'max_installments' => ['nullable', 'integer', 'in:24,40,44,55'],
            'min_down_payment_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allows_balloon_payment' => ['sometimes', 'boolean'],
            'allows_bpp_bonus' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('financing_type');
            $maxInstallments = $this->input('max_installments');

            if (in_array($type, ['installments', 'mixed'], true) && empty($maxInstallments)) {
                $validator->errors()->add('max_installments', 'max_installments es requerido para financiamiento a cuotas.');
            }

            if ($type === 'cash_only' && !empty($maxInstallments)) {
                $validator->errors()->add('max_installments', 'max_installments debe ser null cuando financing_type es cash_only.');
            }
        });
    }
}

