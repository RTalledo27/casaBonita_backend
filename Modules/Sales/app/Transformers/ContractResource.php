<?php

namespace Modules\Sales\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\HumanResources\Transformers\EmployeeResource;
use Modules\CRM\Transformers\ClientResource;
use Modules\Inventory\Transformers\LotResource;


class ContractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'contract_id'    => $this->contract_id,
            'reservation_id' => $this->reservation_id,
            'advisor_id'     => $this->advisor_id,
            'contract_number' => $this->contract_number,
            'sign_date'      => $this->sign_date,
            'total_price'    => $this->total_price,
            'down_payment'   => $this->down_payment,
            'financing_amount' => $this->financing_amount,
            'interest_rate'  => $this->interest_rate,
            'term_months'    => $this->term_months,
            'monthly_payment' => $this->monthly_payment,
            'currency'       => $this->currency,
            'status'         => $this->status,
            'pdf_path'       => $this->pdf_path,
            'previous_contract_id' => $this->previous_contract_id,
            'transferred_amount_from_previous_contract' => $this->transferred_amount_from_previous_contract,
            'financing_type' => $this->financing_type,
            'with_financing' => $this->financing_type === 'WITH_FINANCING',
            
            // Campos financieros migrados desde Lot
            'funding'        => $this->funding,
            'bpp'            => $this->bpp,
            'bfh'            => $this->bfh,
            'initial_quota'  => $this->initial_quota,

            // Campos derivados de relaciones para el frontend
            'client_name'    => $this->getClientName() ?? 'N/A',
            'lot_name'       => $this->getLotName() ?? 'N/A',

            // Relaciones
            'lot'            => new LotResource($this->whenLoaded('lot')),
            'client'         => new ClientResource($this->whenLoaded('client')),
            'reservation'    => new ReservationResource($this->whenLoaded('reservation')),
            'advisor'        => new EmployeeResource($this->whenLoaded('advisor')),
            'schedules'      => PaymentScheduleResource::collection($this->whenLoaded('schedules')),
            'approvals'      => ContractApprovalResource::collection($this->whenLoaded('approvals')),

            // Para la relación recursiva 'previousContract', cargamos solo datos básicos para evitar bucles infinitos
            'previous_contract' => $this->whenLoaded('previousContract', function () {
                return [
                    'contract_id' => $this->previousContract->contract_id,
                    'contract_number' => $this->previousContract->contract_number,
                    'status' => $this->previousContract->status,
                    'total_price' => $this->previousContract->total_price,
                ];
            }),

            // Campos calculados para información financiera
            'financial_summary' => [
                'down_payment_percentage' => $this->total_price > 0 ? round(($this->down_payment / $this->total_price) * 100, 2) : 0,
                'financing_percentage' => $this->total_price > 0 ? round(($this->financing_amount / $this->total_price) * 100, 2) : 0,
                'total_interest' => ($this->monthly_payment * $this->term_months) - $this->financing_amount,
                'total_to_pay' => $this->down_payment + ($this->monthly_payment * $this->term_months),
            ],
        ];
    }
}
