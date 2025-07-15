<?php

namespace Modules\Finance\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'fiscal_year' => $this->fiscal_year,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'total_amount' => $this->total_amount,
            'total_executed' => $this->getTotalExecutedAttribute(),
            'remaining_amount' => $this->getRemainingAmountAttribute(),
            'execution_percentage' => round($this->getExecutionPercentageAttribute(), 2),
            'status' => $this->status,
            'created_by' => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ],
            'approved_by' => [
                'id' => $this->approver?->id,
                'name' => $this->approver?->name,
            ],
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'budget_lines' => BudgetLineResource::collection($this->whenLoaded('budgetLines')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
