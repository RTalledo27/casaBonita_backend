<?php

namespace Modules\Inventory\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManzanaFinancingRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'manzana_id' => $this->manzana_id,
            'financing_type' => $this->financing_type,
            'max_installments' => $this->max_installments,
            'min_down_payment_percentage' => $this->min_down_payment_percentage,
            'allows_balloon_payment' => $this->allows_balloon_payment,
            'allows_bpp_bonus' => $this->allows_bpp_bonus,
            'manzana' => new ManzanaResource($this->whenLoaded('manzana')),
        ];
    }
}

