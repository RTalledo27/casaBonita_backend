<?php

namespace Modules\Finance\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetLineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'budget_id' => $this->budget_id,
            'account' => [
                'id' => $this->account?->id,
                'code' => $this->account?->code,
                'name' => $this->account?->name,
            ],
            'description' => $this->description,
            'budgeted_amount' => $this->budgeted_amount,
            'executed_amount' => $this->executed_amount,
            'remaining_amount' => $this->getRemainingAmountAttribute(),
            'execution_percentage' => round($this->getExecutionPercentageAttribute(), 2),
            'quarter_1' => $this->quarter_1,
            'quarter_2' => $this->quarter_2,
            'quarter_3' => $this->quarter_3,
            'quarter_4' => $this->quarter_4,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
