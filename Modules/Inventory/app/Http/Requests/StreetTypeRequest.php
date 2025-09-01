<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StreetTypeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:street_types,name' . ($this->street_type?->street_type_id ? ',' . $this->street_type->street_type_id . ',street_type_id' : ''),
        ];
        }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
 $permission = $this->street_type ? 'inventory.manzanas.update' : 'inventory.manzanas.create';
        return $this->user()?->can($permission) ?? false;
        }
}
