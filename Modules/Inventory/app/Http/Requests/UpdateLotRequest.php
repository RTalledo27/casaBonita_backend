<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLotRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'manzana_id'           => 'sometimes|exists:manzanas,manzana_id',
            'street_type_id'       => 'sometimes|exists:street_types,street_type_id',
            'num_lot'              => 'sometimes|integer',
            'area_m2'              => 'sometimes|numeric',
            'area_construction_m2' => 'nullable|numeric',
            'total_price'          => 'sometimes|numeric',
            'currency'             => 'sometimes|string|size:3',
            'status'               => 'sometimes|in:disponible,reservado,bloqueado,vendido',
            // Financial fields removed - now handled by Contract requests
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('inventory.lots.update') ?? false;
    }
}
