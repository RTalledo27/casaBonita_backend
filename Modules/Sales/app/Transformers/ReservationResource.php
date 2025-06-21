<?php

namespace Modules\Sales\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Transformers\ClientResource;
use Modules\Inventory\Transformers\lotResource;

class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'reservation_id'  => $this->reservation_id,
            'lot_id'          => $this->lot_id,
            'client_id'       => $this->client_id,
            'reservation_date' => $this->reservation_date,
            'expiration_date' => $this->expiration_date,
            'deposit_amount'  => $this->deposit_amount,
            'deposit_method'  => $this->deposit_method,
            'deposit_reference' => $this->deposit_reference,
            'deposit_paid_at' => $this->deposit_paid_at?->toDateTimeString(),
        ];
    }
}
