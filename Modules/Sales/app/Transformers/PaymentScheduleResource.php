<?php

namespace Modules\Sales\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Transformers\ClientResource;
use Modules\Inventory\Transformers\LotResource;

class PaymentScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'schedule_id' => $this->schedule_id,
            'contract_id' => $this->contract_id,
            'installment_number' => $this->installment_number,
            'due_date'    => $this->due_date,
            'amount'      => $this->amount,
            'amount_paid' => $this->amount_paid,
            'status'      => $this->status,
            'payment_date' => $this->payment_date,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Información del contrato
            'contract'    => new ContractResource($this->whenLoaded('contract')),
            
            // Información del cliente (a través de contract.reservation.client)
            'client_name' => $this->when(
                $this->relationLoaded('contract') && 
                $this->contract?->relationLoaded('reservation') && 
                $this->contract?->reservation?->relationLoaded('client'),
                function() {
                    $client = $this->contract->reservation->client;
                    return trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
                }
            ),
            
            'client_document' => $this->when(
                $this->relationLoaded('contract') && 
                $this->contract?->relationLoaded('reservation') && 
                $this->contract?->reservation?->relationLoaded('client'),
                function() {
                    return $this->contract->reservation->client->doc_number ?? null;
                }
            ),
            
            // Información del lote (a través de contract.reservation.lot)
            'lot_number' => $this->when(
                $this->relationLoaded('contract') && 
                $this->contract?->relationLoaded('reservation') && 
                $this->contract?->reservation?->relationLoaded('lot'),
                function() {
                    return $this->contract->reservation->lot->num_lot ?? null;
                }
            ),
            
            'lot_manzana' => $this->when(
                $this->relationLoaded('contract') && 
                $this->contract?->relationLoaded('reservation') && 
                $this->contract?->reservation?->relationLoaded('lot'),
                function() {
                    return $this->contract->reservation->lot->manzana_id ?? null;
                }
            ),
            
            'lot_area' => $this->when(
                $this->relationLoaded('contract') && 
                $this->contract?->relationLoaded('reservation') && 
                $this->contract?->reservation?->relationLoaded('lot'),
                function() {
                    return $this->contract->reservation->lot->area_m2 ?? null;
                }
            ),
            
            'payments'    => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
