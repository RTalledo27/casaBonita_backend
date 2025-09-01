<?php

namespace Modules\HumanResources\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->employee_id,
            'employee_code' => $this->employee_code,
            'employee_type' => $this->employee_type,
            'base_salary' => $this->base_salary,
            'variable_salary' => $this->variable_salary,
            'commission_percentage' => $this->commission_percentage,
            'individual_goal' => $this->individual_goal,
            'is_commission_eligible' => $this->is_commission_eligible,
            'is_bonus_eligible' => $this->is_bonus_eligible,
            'bank_account' => $this->bank_account,
            'bank_name' => $this->bank_name,
            'bank_cci' => $this->bank_cci,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relationship' => $this->emergency_contact_relationship,
            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'termination_date' => $this->termination_date?->format('Y-m-d'),
            'employment_status' => $this->employment_status,
            'contract_type' => $this->contract_type,
            'work_schedule' => $this->work_schedule,
            'social_security_number' => $this->social_security_number,
            'afp_code' => $this->afp_code,
            'cuspp' => $this->cuspp,
            'health_insurance' => $this->health_insurance,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relaciones
            'user' => $this->whenLoaded('user', function () {
                return [
                    'user_id' => $this->user->user_id,
                    'first_name' => $this->user->first_name,
                    'last_name' => $this->user->last_name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                    'position' => $this->user->position,
                    'department' => $this->user->department,
                    'status' => $this->user->status
                ];
            }),

            'team' => $this->whenLoaded('team', function () {
                return [
                    'team_id' => $this->team->team_id,
                    'team_name' => $this->team->team_name,
                    'team_code' => $this->team->team_code,
                    'monthly_goal' => $this->team->monthly_goal
                ];
            }),

            'supervisor' => $this->whenLoaded('supervisor', function () {
                return [
                    'employee_id' => $this->supervisor->employee_id,
                    'employee_code' => $this->supervisor->employee_code,
                    'full_name' => $this->supervisor->full_name
                ];
            }),

            // Atributos calculados
            'full_name' => $this->full_name,
            'is_advisor' => $this->isAdvisor,

            // Estadísticas (cuando se incluyan)
            'total_commissions' => $this->when(isset($this->total_commissions), $this->total_commissions) ?: $this->whenLoaded('commissions', $this->commissions->sum('commission_amount'), 0),
            'total_bonuses' => $this->when(isset($this->total_bonuses), $this->total_bonuses) ?: $this->whenLoaded('bonuses', $this->bonuses->sum('bonus_amount'), 0),
            'total_earnings' => $this->when(isset($this->total_earnings), $this->total_earnings) ?: ($this->whenLoaded('commissions', $this->commissions->sum('commission_amount'), 0) + $this->whenLoaded('bonuses', $this->bonuses->sum('bonus_amount'), 0)),
            'monthly_sales_count' => $this->when(isset($this->monthly_sales_count), $this->monthly_sales_count),
            'goal_achievement' => $this->when(isset($this->goal_achievement), $this->goal_achievement)
        ];
    }
}
