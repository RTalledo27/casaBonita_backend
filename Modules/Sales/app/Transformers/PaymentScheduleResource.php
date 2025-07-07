<?php

namespace Modules\Sales\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Transformers\ClientResource;
use Modules\Inventory\Transformers\LotResource;

class PaymentScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            
            //
            'schedule_id' => $this->schedule_id,
            'contract_id' => $this->contract_id,
            'due_date'    => $this->due_date,
            'amount'      => $this->amount,
            'status'      => $this->status,
            'contract'    => new ContractResource($this->whenLoaded('contract')),
            'payments'    => PaymentResource::collection($this->whenLoaded('payments')),

        ];
        }
}
