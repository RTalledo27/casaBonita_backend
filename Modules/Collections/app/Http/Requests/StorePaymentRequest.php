<?php

namespace Modules\Collections\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Collections\Models\CustomerPayment;

class StorePaymentRequest extends FormRequest
{
    public function authorize()
    {
        return true; // La autorización se maneja en el controlador
    }

    public function rules()
    {
        $rules = [
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required|in:' . implode(',', [
                CustomerPayment::METHOD_CASH,
                CustomerPayment::METHOD_TRANSFER,
                CustomerPayment::METHOD_CHECK,
                CustomerPayment::METHOD_C_CARD,
                CustomerPayment::METHOD_D_CARD,
                CustomerPayment::METHOD_YAPE,
                CustomerPayment::METHOD_PLIN,
                CustomerPayment::METHOD_OTHER
            ]),
            'notes' => 'nullable|string|max:500'
        ];

        // Validar número de referencia para ciertos métodos de pago
        if (in_array($this->payment_method, [CustomerPayment::METHOD_TRANSFER, CustomerPayment::METHOD_CHECK])) {
            $rules['reference_number'] = 'required|string|max:100';
        } else {
            $rules['reference_number'] = 'nullable|string|max:100';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'amount.required' => 'El monto del pago es obligatorio',
            'amount.numeric' => 'El monto debe ser un número válido',
            'amount.min' => 'El monto debe ser mayor a 0',
            'payment_date.required' => 'La fecha de pago es obligatoria',
            'payment_date.date' => 'La fecha de pago debe ser válida',
            'payment_date.before_or_equal' => 'La fecha de pago no puede ser futura',
            'payment_method.required' => 'El método de pago es obligatorio',
            'payment_method.in' => 'El método de pago seleccionado no es válido',
            'reference_number.required' => 'El número de referencia es obligatorio para este método de pago',
            'reference_number.max' => 'El número de referencia no puede exceder 100 caracteres',
            'notes.max' => 'Las notas no pueden exceder 500 caracteres'
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar que el monto no exceda el saldo pendiente
            $accountReceivable = $this->route('accountReceivable');
            if ($accountReceivable && $this->amount > $accountReceivable->outstanding_amount) {
                $validator->errors()->add('amount', 'El monto del pago excede el saldo pendiente de ' . number_format($accountReceivable->outstanding_amount, 2));
            }
        });
    }
}
