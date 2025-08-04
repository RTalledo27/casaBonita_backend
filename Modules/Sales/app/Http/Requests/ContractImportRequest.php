<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ContractImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Ajustar según los permisos necesarios
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:51200' // 50MB máximo
            ],
            'validate_only' => 'sometimes|boolean',
            'skip_duplicates' => 'sometimes|boolean',
            'update_existing' => 'sometimes|boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'El archivo es requerido para la importación',
            'file.file' => 'Debe seleccionar un archivo válido',
            'file.mimes' => 'El archivo debe ser de tipo Excel (.xlsx, .xls) o CSV (.csv)',
            'file.max' => 'El archivo no debe exceder los 50MB de tamaño',
            'validate_only.boolean' => 'El parámetro validate_only debe ser verdadero o falso',
            'skip_duplicates.boolean' => 'El parámetro skip_duplicates debe ser verdadero o falso',
            'update_existing.boolean' => 'El parámetro update_existing debe ser verdadero o falso'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'file' => 'archivo',
            'validate_only' => 'solo validar',
            'skip_duplicates' => 'omitir duplicados',
            'update_existing' => 'actualizar existentes'
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error de validación en los datos enviados',
                'errors' => $validator->errors(),
                'error_count' => $validator->errors()->count()
            ], 422)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir strings a boolean si es necesario
        if ($this->has('validate_only')) {
            $this->merge([
                'validate_only' => filter_var($this->validate_only, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false
            ]);
        }

        if ($this->has('skip_duplicates')) {
            $this->merge([
                'skip_duplicates' => filter_var($this->skip_duplicates, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false
            ]);
        }

        if ($this->has('update_existing')) {
            $this->merge([
                'update_existing' => filter_var($this->update_existing, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false
            ]);
        }
    }

    /**
     * Get validated data with default values
     */
    public function getValidatedData(): array
    {
        $validated = $this->validated();
        
        return array_merge([
            'validate_only' => false,
            'skip_duplicates' => true,
            'update_existing' => false
        ], $validated);
    }
}