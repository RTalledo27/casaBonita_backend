<?php

namespace Modules\Sales\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Security\Transformers\UserResource;

class ContractApprovalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'approval_id' => $this->approval_id,
            'contract_id' => $this->contract_id,
            'user_id'     => $this->user_id,
            'status'      => $this->status,
            'approved_at' => $this->approved_at,
            'comments'    => $this->comments,
            'contract'    => new ContractResource($this->whenLoaded('contract')),
            'approver'    => new UserResource($this->whenLoaded('approver')) ];
    }
}
