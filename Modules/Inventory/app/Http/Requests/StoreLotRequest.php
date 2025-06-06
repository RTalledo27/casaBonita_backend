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
            'manzana_id'           => 'required|exists:manzana,manzana_id',
            'street_type_id'       => 'required|exists:street_type,street_type_id',
            'num_lot'              => 'required|integer',
            'area_m2'              => 'required|numeric',
            'area_construction_m2' => 'nullable|numeric',
            'total_price'          => 'required|numeric',
            'funding'              => 'nullable|numeric',
            'BPP'                  => 'nullable|numeric',
            'BFH'                  => 'nullable|numeric',
            'initial_quota'        => 'nullable|numeric',
            'currency'             => 'required|string|size:3',
            'status'               => 'required|in:disponible,reservado,vendido',
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
