<?php

namespace Modules\Collections\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Collections\Models\AccountReceivable;

class StoreAccountReceivableRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('create', AccountReceivable::class);
    }

    public function rules()
    {
        return [
            'client_id' => 'required|exists:clients,client_id',
            'contract_id' => 'nullable|exists:contracts,contract_id',
            'invoice_number' => 'nullable|string|max:50',
            'description' => 'required|string|max:500',
            'original_amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:PEN,USD',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'assigned_collector_id' => 'nullable|exists:users,user_id',
            'notes' => 'nullable|string|max:1000',
            'create_journal_entry' => 'boolean'
        ];
    }

    public function messages()
    {
        return [
            'client_id.required' => 'El cliente es obligatorio',
            'client_id.exists' => 'El cliente seleccionado no existe',
            'contract_id.exists' => 'El contrato seleccionado no existe',
            'description.required' => 'La descripción es obligatoria',
            'description.max' => 'La descripción no puede exceder 500 caracteres',
            'original_amount.required' => 'El monto original es obligatorio',
            'original_amount.numeric' => 'El monto debe ser un número válido',
            'original_amount.min' => 'El monto debe ser mayor a 0',
            'currency.required' => 'La moneda es obligatoria',
            'currency.in' => 'La moneda debe ser PEN o USD',
            'issue_date.required' => 'La fecha de emisión es obligatoria',
            'issue_date.date' => 'La fecha de emisión debe ser válida',
            'due_date.required' => 'La fecha de vencimiento es obligatoria',
            'due_date.date' => 'La fecha de vencimiento debe ser válida',
            'due_date.after_or_equal' => 'La fecha de vencimiento debe ser igual o posterior a la fecha de emisión',
            'assigned_collector_id.exists' => 'El cobrador seleccionado no existe',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres'
        ];
    }
}
