<?php

namespace Modules\HumanResources\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BonusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'bonus_id' => $this->bonus_id,
            'employee_id' => $this->employee_id,
            'bonus_type_id' => $this->bonus_type_id,
            'bonus_goal_id' => $this->bonus_goal_id,
            'bonus_name' => $this->bonus_name,
            'bonus_amount' => $this->bonus_amount,
            'target_amount' => $this->target_amount,
            'achieved_amount' => $this->achieved_amount,
            'achievement_percentage' => $this->achievement_percentage,
            'payment_status' => $this->payment_status,
            'payment_status_label' => $this->getPaymentStatusLabel(),
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'period_month' => $this->period_month,
            'period_year' => $this->period_year,
            'period_quarter' => $this->period_quarter,
            'period_label' => $this->period_label,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'requires_approval' => $this->requiresApproval(),
            'can_be_paid' => $this->canBePaid(),
            'status_badge_class' => $this->status_badge_class,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relaciones
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'employee_id' => $this->employee->employee_id,
                    'employee_code' => $this->employee->employee_code,
                    'employee_type' => $this->employee->employee_type,
                    'full_name' => $this->employee->full_name,
                    'is_advisor' => $this->employee->isAdvisor,
                    'base_salary' => $this->employee->base_salary,
                    'individual_goal' => $this->employee->individual_goal,
                    'employment_status' => $this->employee->employment_status,
                    'user' => $this->when($this->employee->relationLoaded('user'), function () {
                        return [
                            'user_id' => $this->employee->user->user_id,
                            'first_name' => $this->employee->user->first_name,
                            'last_name' => $this->employee->user->last_name,
                            'email' => $this->employee->user->email,
                            'avatar' => $this->employee->user->avatar,
                        ];
                    }),
                    'team' => $this->when($this->employee->relationLoaded('team'), function () {
                        return [
                            'team_id' => $this->employee->team->team_id,
                            'team_name' => $this->employee->team->team_name,
                            'team_code' => $this->employee->team->team_code,
                        ];
                    }),
                ];
            }),

            'bonus_type' => $this->whenLoaded('bonusType', function () {
                return [
                    'bonus_type_id' => $this->bonusType->bonus_type_id,
                    'type_code' => $this->bonusType->type_code,
                    'type_name' => $this->bonusType->type_name,
                    'description' => $this->bonusType->description,
                    'calculation_method' => $this->bonusType->calculation_method,
                    'calculation_method_label' => $this->bonusType->calculation_method_label,
                    'is_automatic' => $this->bonusType->is_automatic,
                    'requires_approval' => $this->bonusType->requires_approval,
                    'frequency' => $this->bonusType->frequency,
                    'frequency_label' => $this->bonusType->frequency_label,
                    'applicable_employee_types' => $this->bonusType->applicable_employee_types,
                ];
            }),

            'bonus_goal' => $this->whenLoaded('bonusGoal', function () {
                return [
                    'bonus_goal_id' => $this->bonusGoal->bonus_goal_id,
                    'goal_name' => $this->bonusGoal->goal_name,
                    'min_achievement' => $this->bonusGoal->min_achievement,
                    'max_achievement' => $this->bonusGoal->max_achievement,
                    'bonus_amount' => $this->bonusGoal->bonus_amount,
                    'bonus_percentage' => $this->bonusGoal->bonus_percentage,
                    'employee_type' => $this->bonusGoal->employee_type,
                    'team_id' => $this->bonusGoal->team_id,
                    'valid_from' => $this->bonusGoal->valid_from?->format('Y-m-d'),
                    'valid_until' => $this->bonusGoal->valid_until?->format('Y-m-d'),
                ];
            }),

            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'employee_id' => $this->creator->employee_id,
                    'employee_code' => $this->creator->employee_code,
                    'full_name' => $this->creator->full_name,
                    'user' => $this->when($this->creator->relationLoaded('user'), function () {
                        return [
                            'first_name' => $this->creator->user->first_name,
                            'last_name' => $this->creator->user->last_name,
                            'email' => $this->creator->user->email,
                        ];
                    }),
                ];
            }),

            'approver' => $this->whenLoaded('approver', function () {
                return [
                    'employee_id' => $this->approver->employee_id,
                    'employee_code' => $this->approver->employee_code,
                    'full_name' => $this->approver->full_name,
                    'user' => $this->when($this->approver->relationLoaded('user'), function () {
                        return [
                            'first_name' => $this->approver->user->first_name,
                            'last_name' => $this->approver->user->last_name,
                            'email' => $this->approver->user->email,
                        ];
                    }),
                ];
            }),
        ];
    }

    /**
     * Información adicional para vistas específicas
     */
    public function withCalculations(): array
    {
        $data = $this->toArray(request());

        // Agregar cálculos adicionales
        $data['calculations'] = [
            'achievement_vs_target' => $this->target_amount > 0
                ? ($this->achieved_amount / $this->target_amount) * 100
                : null,
            'bonus_vs_salary' => $this->employee && $this->employee->base_salary > 0
                ? ($this->bonus_amount / $this->employee->base_salary) * 100
                : null,
            'days_since_created' => $this->created_at ? $this->created_at->diffInDays(now()) : null,
            'days_since_approved' => $this->approved_at ? $this->approved_at->diffInDays(now()) : null,
            'is_overdue' => $this->payment_status === 'pendiente' && $this->created_at->diffInDays(now()) > 30,
        ];

        return $data;
    }

    /**
     * Versión simplificada para listados
     */
    public function simplified(): array
    {
        return [
            'bonus_id' => $this->bonus_id,
            'employee_name' => $this->employee?->full_name,
            'bonus_type_name' => $this->bonusType?->type_name,
            'bonus_amount' => $this->bonus_amount,
            'payment_status' => $this->payment_status,
            'payment_status_label' => $this->getPaymentStatusLabel(),
            'period_label' => $this->period_label,
            'requires_approval' => $this->requiresApproval(),
            'can_be_paid' => $this->canBePaid(),
            'status_badge_class' => $this->status_badge_class,
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }

    /**
     * Información para dashboard/reportes
     */
    public function forDashboard(): array
    {
        return [
            'bonus_id' => $this->bonus_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee?->full_name,
            'employee_type' => $this->employee?->employee_type,
            'team_name' => $this->employee?->team?->team_name,
            'bonus_type_name' => $this->bonusType?->type_name,
            'bonus_amount' => $this->bonus_amount,
            'achievement_percentage' => $this->achievement_percentage,
            'payment_status' => $this->payment_status,
            'period_month' => $this->period_month,
            'period_year' => $this->period_year,
            'period_quarter' => $this->period_quarter,
            'created_at' => $this->created_at?->format('Y-m-d'),
            'payment_date' => $this->payment_date?->format('Y-m-d'),
        ];
    }

    private function getPaymentStatusLabel(): string
    {
        return match ($this->payment_status) {
            'pendiente' => 'Pendiente',
            'pagado' => 'Pagado',
            'cancelado' => 'Cancelado',
            default => 'Desconocido'
        };
    }
}
