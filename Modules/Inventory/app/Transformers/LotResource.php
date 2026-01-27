<?php

namespace Modules\Inventory\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'lot_id'               => $this->lot_id,
            'manzana_id'           => $this->manzana_id,
            'street_type_id'       => $this->street_type_id,
            'num_lot'              => $this->num_lot,
            'area_m2'              => $this->area_m2,
            'area_construction_m2' => $this->area_construction_m2,
            'total_price'          => $this->total_price,  // Precio base del lote
            'currency'             => $this->currency,
            'status'               => $this->status,
            // Campos financieros removidos: funding, BPP, BFH, initial_quota
            
            // Relaciones
            'manzana'              => new ManzanaResource($this->whenLoaded('manzana')),
            'street_type'          => new StreetTypeResource($this->whenLoaded('streetType')),
            'media'                => LotMediaResource::collection($this->whenLoaded('media')),
        ];    }
}
