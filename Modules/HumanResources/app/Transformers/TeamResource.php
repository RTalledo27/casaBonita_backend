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
            'monthly_goal' => $this->monthly_goal,
            'office_id' => $this->office_id,
            'team_leader_id' => $this->team_leader_id,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relationships
            'office' => $this->whenLoaded('office', function () {
                return [
                    'office_id' => $this->office->office_id,
                    'name' => $this->office->name,
                    'code' => $this->office->code,
                    'city' => $this->office->city,
                ];
            }),
            'leader' => $this->whenLoaded('leader', function () {
                $leader = $this->leader;
                $user = $leader->user;
                return [
                    'employee_id' => $leader->employee_id,
                    'employee_code' => $leader->employee_code,
                    'first_name' => $user?->first_name ?? $leader->first_name,
                    'last_name' => $user?->last_name ?? $leader->last_name,
                    'full_name' => $leader->full_name ?: trim(($leader->first_name ?? '') . ' ' . ($leader->last_name ?? '')),
                    'email' => $user?->email ?? $leader->email,
                    'phone' => $user?->phone ?? $leader->phone,
                    'employee_type' => $leader->employee_type,
                    'status' => $leader->employment_status ?? $leader->status,
                ];
            }),
            
            'employees' => $this->whenLoaded('employees', function () {
                return $this->employees->map(function ($employee) {
                    $user = $employee->user;
                    return [
                        'employee_id' => $employee->employee_id,
                        'employee_code' => $employee->employee_code,
                        'first_name' => $user?->first_name ?? $employee->first_name,
                        'last_name' => $user?->last_name ?? $employee->last_name,
                        'full_name' => $employee->full_name ?: trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
                        'email' => $user?->email ?? $employee->email,
                        'phone' => $user?->phone ?? $employee->phone,
                        'employee_type' => $employee->employee_type,
                        'hire_date' => $employee->hire_date,
                        'status' => $employee->employment_status ?? $employee->status,
                    ];
                });
            }),
            
            'employees_count' => $this->whenLoaded('employees', function () {
                return $this->employees->count();
            }),
        ];
    }
}