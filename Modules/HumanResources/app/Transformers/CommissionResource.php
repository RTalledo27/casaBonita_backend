<?php

namespace Modules\HumanResources\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'commission_id' => $this->commission_id,
            'commission_type' => $this->commission_type,
            'sale_amount' => $this->sale_amount,
            'installment_plan' => $this->installment_plan,
            'commission_percentage' => $this->commission_percentage,
            'commission_amount' => $this->commission_amount,
            'payment_status' => $this->payment_status,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'period_month' => $this->period_month,
            'period_year' => $this->period_year,
            'is_payable' => $this->is_payable,
            'parent_commission_id' => $this->parent_commission_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),

            // Relaciones
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'employee_id' => $this->employee->employee_id,
                    'employee_code' => $this->employee->employee_code,
                    'full_name' => $this->employee->full_name,
                    'employee_type' => $this->employee->employee_type
                ];
            }),

            'contract' => $this->whenLoaded('contract', function () {
                return [
                    'contract_id' => $this->contract->contract_id,
                    'contract_number' => $this->contract->contract_number,
                    'total_price' => $this->contract->total_price,
                    'sign_date' => $this->contract->sign_date?->format('Y-m-d'),
                    'status' => $this->contract->status
                ];
            })
        ];
    }
}
