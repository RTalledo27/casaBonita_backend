<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LotMediaRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'lot_id'   => 'required|exists:lots,lot_id',
            //'url'      => 'required|url',
            'type'     => 'required|in:foto,plano,video,doc',
            'position' => 'nullable|integer|min:1',
        ];    
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.media.manage') ?? false;
    }
}
