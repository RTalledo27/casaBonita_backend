<?php

namespace Modules\HumanResources\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BonusGoalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'bonus_goal_id' => $this->bonus_goal_id,
            'bonus_type_id' => $this->bonus_type_id,
            'goal_name' => $this->goal_name,
            'description' => $this->description,
            'target_value' => $this->target_value,
            'min_achievement' => $this->min_achievement,
            'max_achievement' => $this->max_achievement,
            'bonus_amount' => $this->bonus_amount,
            'bonus_percentage' => $this->bonus_percentage,
            'employee_type' => $this->employee_type,
            'team_id' => $this->team_id,
            'office_id' => $this->office_id,
            'is_active' => $this->is_active,
            'valid_from' => $this->valid_from,
            'valid_until' => $this->valid_until,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relationships
            'bonus_type' => $this->whenLoaded('bonusType', function () {
                return [
                    'bonus_type_id' => $this->bonusType->bonus_type_id,
                    'type_code' => $this->bonusType->type_code,
                    'type_name' => $this->bonusType->type_name,
                ];
            }),
            'team' => $this->whenLoaded('team', function () {
                return [
                    'team_id' => $this->team->team_id,
                    'team_name' => $this->team->team_name,
                ];
            }),
            'office' => $this->whenLoaded('office', function () {
                return [
                    'office_id' => $this->office->office_id,
                    'name' => $this->office->name,
                ];
            }),
        ];
    }
}
