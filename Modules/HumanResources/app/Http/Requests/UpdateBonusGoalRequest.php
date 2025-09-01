<?php

namespace Modules\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBonusGoalRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $bonusGoalId = $this->route('bonus_goal');
        return [
            'bonus_type_id' => 'sometimes|integer|exists:bonus_types,bonus_type_id',
            'goal_name' => 'sometimes|string|max:255',
            'min_achievement' => 'sometimes|numeric|min:0',
            'max_achievement' => 'nullable|numeric|gte:min_achievement',
            'bonus_amount' => 'nullable|numeric|min:0',
            'bonus_percentage' => 'nullable|numeric|min:0|max:100',
            'employee_type' => 'nullable|string|max:50',
            'team_id' => 'nullable|integer|exists:teams,team_id',
            'is_active' => 'sometimes|boolean',
            'valid_from' => 'sometimes|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from'
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
