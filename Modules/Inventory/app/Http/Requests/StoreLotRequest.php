<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLotRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'manzana_id'           => 'required|exists:manzanas,manzana_id',
            'street_type_id'       => 'required|exists:street_types,street_type_id',
            'num_lot'              => 'required|integer',
            'area_m2'              => 'required|numeric',
            'area_construction_m2' => 'nullable|numeric',
            'total_price'          => 'required|numeric',
            'currency'             => 'required|string|size:3',
            'status'               => 'required|in:disponible,reservado,bloqueado,vendido',
            // Financial fields removed - now handled by Contract requests
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.lots.store') ?? false;
    }

}
