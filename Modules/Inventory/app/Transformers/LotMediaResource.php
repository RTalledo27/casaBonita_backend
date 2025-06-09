<?php

namespace Modules\Inventory\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LotMediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'media_id'   => $this->media_id,
            'lot_id'     => $this->lot_id,
            'url'        => $this->url,
            'type'       => $this->type,
            'position'   => $this->position,
            'uploaded_at' => $this->uploaded_at,
        ];
    }
}
