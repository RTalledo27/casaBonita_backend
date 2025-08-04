<?php

namespace Modules\Sales\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Transformers\ClientResource;
use Modules\HumanResources\Transformers\EmployeeResource;
use Modules\Inventory\Http\Resources\LotResource;

class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'reservation_id' => $this->reservation_id,
            'lot_id' => $this->lot_id,
            'client_id' => $this->client_id,
            'advisor_id' => $this->advisor_id,
            'reservation_date' => $this->reservation_date?->format('Y-m-d'),
            'expiration_date' => $this->expiration_date?->format('Y-m-d'),
            'deposit_amount' => $this->deposit_amount,
            'deposit_method' => $this->deposit_method,
            'status' => $this->status,
            
            // Relaciones
            'lot' => new LotResource($this->whenLoaded('lot')),
            'client' => new ClientResource($this->whenLoaded('client')),
            'advisor' => new EmployeeResource($this->whenLoaded('advisor')),
        ];
    }
}