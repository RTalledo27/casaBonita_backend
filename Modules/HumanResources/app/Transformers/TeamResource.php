<?php

namespace Modules\HumanResources\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'team_id' => $this->team_id,
            'team_name' => $this->team_name,
            'team_code' => $this->team_code,
            'description' => $this->description,
            'team_leader_id' => $this->team_leader_id,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relationships
            'leader' => $this->whenLoaded('leader', function () {
                return [
                    'employee_id' => $this->leader->employee_id,
                    'employee_code' => $this->leader->employee_code,
                    'first_name' => $this->leader->first_name,
                    'last_name' => $this->leader->last_name,
                    'full_name' => $this->leader->first_name . ' ' . $this->leader->last_name,
                ];
            }),
            
            'employees' => $this->whenLoaded('employees', function () {
                return $this->employees->map(function ($employee) {
                    return [
                        'employee_id' => $employee->employee_id,
                        'employee_code' => $employee->employee_code,
                        'first_name' => $employee->first_name,
                        'last_name' => $employee->last_name,
                        'full_name' => $employee->first_name . ' ' . $employee->last_name,
                        'employee_type' => $employee->employee_type,
                        'status' => $employee->status,
                    ];
                });
            }),
            
            'employees_count' => $this->whenLoaded('employees', function () {
                return $this->employees->count();
            }),
        ];
    }
}