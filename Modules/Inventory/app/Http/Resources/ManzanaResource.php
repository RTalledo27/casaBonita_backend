<?php

namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ManzanaResource extends JsonResource
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
            'manzana_id' => $this->manzana_id,
            'name' => $this->name,
            'project_id' => $this->project_id,
            'description' => $this->description,
        ];
    }
}