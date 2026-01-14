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
            'external_id'          => $this->external_id,
            'external_code'        => $this->external_code,
            'external_sync_at'     => $this->external_sync_at,
            'external'             => [
                'source' => $this->external_data['source'] ?? null,
                'frontage' => $this->external_data['unit']['frontage'] ?? null,
                'depth' => $this->external_data['unit']['depth'] ?? null,
                'dimensions' => $this->external_data['unit']['dimensions'] ?? null,
                'orientation' => $this->external_data['unit']['orientation'] ?? null,
                'is_corner' => $this->external_data['unit']['isCorner'] ?? null,
                'price_per_sqm' => $this->external_data['unit']['pricePerSqm'] ?? null,
                'remarks' => $this->external_data['unit']['remarks'] ?? null,
                'unit' => $request->boolean('include_external_data') ? ($this->external_data['unit'] ?? null) : null,
            ],
            'financial_template'   => $this->whenLoaded('financialTemplate', function () {
                return [
                    'precio_lista' => $this->financialTemplate->precio_lista,
                    'descuento' => $this->financialTemplate->descuento,
                    'precio_venta' => $this->financialTemplate->precio_venta,
                    'precio_contado' => $this->financialTemplate->precio_contado,
                    'cuota_inicial' => $this->financialTemplate->cuota_inicial,
                    'ci_fraccionamiento' => $this->financialTemplate->ci_fraccionamiento,
                    'cuota_balon' => $this->financialTemplate->cuota_balon,
                    'bono_bpp' => $this->financialTemplate->bono_bpp,
                    'installments_24' => $this->financialTemplate->installments_24,
                    'installments_40' => $this->financialTemplate->installments_40,
                    'installments_44' => $this->financialTemplate->installments_44,
                    'installments_55' => $this->financialTemplate->installments_55,
                ];
            }),
            // Campos financieros removidos: funding, BPP, BFH, initial_quota
            
            // Relaciones
            'manzana'              => new ManzanaResource($this->whenLoaded('manzana')),
            'street_type'          => new StreetTypeResource($this->whenLoaded('streetType')),
            'media'                => LotMediaResource::collection($this->whenLoaded('media')),
        ];    }
}
