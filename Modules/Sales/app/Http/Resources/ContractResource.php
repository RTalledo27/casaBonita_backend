<?php

namespace Modules\Sales\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\HumanResources\Transformers\EmployeeResource;

class ContractResource extends JsonResource
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
            'contract_id' => $this->contract_id,
            'contract_number' => $this->contract_number,
            'reservation_id' => $this->reservation_id,
            'advisor_id' => $this->advisor_id,
            'previous_contract_id' => $this->previous_contract_id,
            'sign_date' => $this->sign_date?->format('Y-m-d'),
            'total_price' => $this->total_price,
            'down_payment' => $this->down_payment,
            'financing_amount' => $this->financing_amount,
            'interest_rate' => $this->interest_rate,
            'term_months' => $this->term_months,
            'monthly_payment' => $this->monthly_payment,
            'balloon_payment' => $this->balloon_payment,
            'currency' => $this->currency,
            'status' => $this->status,
            'transferred_amount_from_previous_contract' => $this->transferred_amount_from_previous_contract,
            'financing_type' => $this->financing_type,
            'with_financing' => $this->financing_type === 'WITH_FINANCING',
            
            // Nuevos campos financieros migrados desde Lote:
            'funding' => $this->funding,
            'bpp' => $this->bpp,
            'bfh' => $this->bfh,
            'initial_quota' => $this->initial_quota,
            
            // Relaciones
            'reservation' => new ReservationResource($this->whenLoaded('reservation')),
            'advisor' => new EmployeeResource($this->whenLoaded('advisor')),
            'previous_contract' => new ContractResource($this->whenLoaded('previousContract')),
            'client' => $this->whenLoaded('client', function() {
                return [
                    'client_id' => $this->client->client_id,
                    'first_name' => $this->client->first_name,
                    'last_name' => $this->client->last_name,
                    'doc_type' => $this->client->doc_type,
                    'doc_number' => $this->client->doc_number,
                    'email' => $this->client->email,
                    'primary_phone' => $this->client->primary_phone,
                ];
            }),
            
            // Información del lote (usando métodos del modelo)
            'lot_name' => $this->getLotName() ?? 'N/A',
            'manzana_name' => $this->getManzanaName() ?? 'No especificado',
            'area_m2' => $this->getArea() ?? null,
            'client_name' => $this->getClientName(),
            'advisor_name' => $this->getAdvisor() ? ($this->getAdvisor()->user->first_name ?? '') . ' ' . ($this->getAdvisor()->user->last_name ?? '') : 'Sin asesor',
        ];
    }
}