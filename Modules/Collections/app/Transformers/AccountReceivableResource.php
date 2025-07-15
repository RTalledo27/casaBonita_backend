<?php

namespace Modules\Collections\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountReceivableResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'ar_id' => $this->ar_id,
            'ar_number' => $this->ar_number,
            'invoice_number' => $this->invoice_number,
            'description' => $this->description,
            'original_amount' => number_format($this->original_amount, 2),
            'outstanding_amount' => number_format($this->outstanding_amount, 2),
            'currency' => $this->currency,
            'issue_date' => $this->issue_date->format('Y-m-d'),
            'due_date' => $this->due_date->format('Y-m-d'),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'aging_days' => $this->aging_days,
            'aging_range' => $this->aging_range,
            'is_overdue' => $this->is_overdue,
            'payment_percentage' => round($this->payment_percentage, 2),
            'notes' => $this->notes,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            
            // Relaciones
            'client' => $this->whenLoaded('client', function () {
                return [
                    'client_id' => $this->client->client_id,
                    'name' => $this->client->name,
                    'document_number' => $this->client->document_number,
                    'email' => $this->client->email,
                    'phone' => $this->client->phone
                ];
            }),
            
            'contract' => $this->whenLoaded('contract', function () {
                return [
                    'contract_id' => $this->contract->contract_id,
                    'contract_number' => $this->contract->contract_number,
                    'property_name' => $this->contract->property->name ?? null
                ];
            }),
            
            'collector' => $this->whenLoaded('collector', function () {
                return [
                    'user_id' => $this->collector->user_id,
                    'name' => $this->collector->name,
                    'email' => $this->collector->email
                ];
            }),
            
            'payments' => CustomerPaymentResource::collection($this->whenLoaded('payments')),
            'payments_count' => $this->payments_count ?? $this->payments->count(),
            'total_payments' => $this->whenLoaded('payments', function () {
                return number_format($this->payments->sum('amount'), 2);
            })
        ];
    }

    private function getStatusLabel()
    {
        $labels = [
            'PENDING' => 'Pendiente',
            'PARTIAL' => 'Parcial',
            'PAID' => 'Pagado',
            'OVERDUE' => 'Vencido',
            'CANCELLED' => 'Cancelado'
        ];

        return $labels[$this->status] ?? $this->status;
    }
}
