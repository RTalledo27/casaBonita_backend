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
            'manzana_id'           => 'sometimes|exists:manzana,manzana_id',
            'street_type_id'       => 'sometimes|exists:street_type,street_type_id',
            'num_lot'              => 'sometimes|integer',
            'area_m2'              => 'sometimes|numeric',
            'area_construction_m2' => 'nullable|numeric',
            'total_price'          => 'sometimes|numeric',
            'funding'              => 'nullable|numeric',
            'BPP'                  => 'nullable|numeric',
            'BFH'                  => 'nullable|numeric',
            'initial_quota'        => 'nullable|numeric',
            'currency'             => 'sometimes|string|size:3',
            'status'               => 'sometimes|in:disponible,reservado,vendido',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('inventory.lots.update') ?? false;
    }
}
