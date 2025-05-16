<?php

namespace Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SpouseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
           // 'client_id'  => 'required|exists:clients,client_id',
            'partner_id' => 'required|exists:clients,client_id|different:client_id',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('crm.clients.update');
        //PUEDE CAMBIARSE EL NOMBRE DEL PERMISO LUEGO
    }
}
