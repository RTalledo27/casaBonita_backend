<?php

namespace Modules\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $teamId = $this->route('id');
        
        return [
            'team_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('teams', 'team_name')->ignore($teamId, 'team_id')
            ],
            'team_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('teams', 'team_code')->ignore($teamId, 'team_id')
            ],
            'description' => 'nullable|string|max:1000',
            'monthly_goal' => 'nullable|numeric|min:0',
            'team_leader_id' => 'nullable|exists:employees,employee_id',
            'status' => 'required|in:active,inactive',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'team_name.required' => 'El nombre del equipo es obligatorio.',
            'team_name.unique' => 'Ya existe un equipo con este nombre.',
            'team_code.unique' => 'Ya existe un equipo con este código.',
            'monthly_goal.numeric' => 'La meta mensual debe ser un número.',
            'monthly_goal.min' => 'La meta mensual no puede ser negativa.',
            'team_leader_id.exists' => 'El líder seleccionado no existe.',
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado debe ser activo o inactivo.',
        ];
    }
}