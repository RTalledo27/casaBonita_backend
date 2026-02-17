<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConvertReservationRequest extends FormRequest
{
  
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_number' => 'required|string|max:255|unique:contracts,contract_number',
            'sign_date' => 'required|date',
            'total_price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'currency' => 'required|string|max:3',

            // Campos financieros principales
            'down_payment' => 'required|numeric|min:0',
            'financing_amount' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'term_months' => 'required|integer|min:1|max:360',
            'monthly_payment' => 'nullable|numeric|min:0',

            // Campos financieros adicionales (bonos, cuotas especiales)
            'initial_quota' => 'nullable|numeric|min:0',
            'balloon_payment' => 'nullable|numeric|min:0',
            'bpp' => 'nullable|numeric|min:0',
            'bfh' => 'nullable|numeric|min:0',
            'funding' => 'nullable|numeric|min:0',

            // Generación automática de cronograma
            'schedule_start_date' => 'nullable|date',
            'schedule_frequency' => 'nullable|in:monthly,biweekly,weekly',

            'approvers'       => 'sometimes|array',
            'approvers.*'     => 'integer|exists:users,user_id',
        ];
    }

    public function messages(): array
    {
        return [
            'down_payment.required' => 'El enganche es obligatorio.',
            'down_payment.numeric' => 'El enganche debe ser un número.',
            'down_payment.min' => 'El enganche debe ser mayor o igual a 0.',

            'financing_amount.required' => 'El monto financiado es obligatorio.',
            'financing_amount.numeric' => 'El monto financiado debe ser un número.',
            'financing_amount.min' => 'El monto financiado debe ser mayor o igual a 0.',

            'interest_rate.required' => 'La tasa de interés es obligatoria.',
            'interest_rate.numeric' => 'La tasa de interés debe ser un número.',
            'interest_rate.min' => 'La tasa de interés debe ser mayor o igual a 0.',
            'interest_rate.max' => 'La tasa de interés no puede ser mayor a 100%.',

            'term_months.required' => 'El plazo en meses es obligatorio.',
            'term_months.integer' => 'El plazo debe ser un número entero.',
            'term_months.min' => 'El plazo debe ser al menos 1 mes.',
            'term_months.max' => 'El plazo no puede ser mayor a 360 meses.',

            'monthly_payment.numeric' => 'El pago mensual debe ser un número.',
            'monthly_payment.min' => 'El pago mensual debe ser mayor o igual a 0.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $downPayment = $this->input('down_payment', 0);
            $financingAmount = $this->input('financing_amount', 0);
            $totalPrice = $this->input('total_price', 0);
            $discount = $this->input('discount', 0);

            $effectivePrice = $totalPrice - $discount;

            if (abs(($downPayment + $financingAmount) - $effectivePrice) > 0.01) {
                $validator->errors()->add(
                    'financing_amount',
                    'La suma del enganche y el monto financiado debe ser igual al precio total menos el descuento.'
                );
            }
        });
    }
}
