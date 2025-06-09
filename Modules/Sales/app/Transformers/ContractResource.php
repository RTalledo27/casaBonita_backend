<?php

namespace Modules\Sales\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'contract_id'    => $this->contract_id,
            'reservation_id' => $this->reservation_id,
            'contract_number' => $this->contract_number,
            'sign_date'      => $this->sign_date,
            'total_price'    => $this->total_price,
            'currency'       => $this->currency,
            'status'         => $this->status,
            'reservation'    => new ReservationResource($this->whenLoaded('reservation')),
            'schedules'      => PaymentScheduleResource::collection($this->whenLoaded('schedules')),
            'pdf_path'       => $this->pdf_path,
            'approvals'      => ContractApprovalResource::collection($this->whenLoaded('approvals')),

        ];
    }
}
