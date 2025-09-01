<?php

namespace Modules\Collections\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'payment_id' => $this->payment_id,
            'payment_number' => $this->payment_number,
            'payment_date' => $this->payment_date->format('Y-m-d'),
            'amount' => number_format($this->amount, 2),
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->getPaymentMethodLabel(),
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),

            // Relaciones
            'client' => $this->whenLoaded('client', function () {
                return [
                    'client_id' => $this->client->client_id,
                    'name' => $this->client->name,
                    'document_number' => $this->client->document_number
                ];
            }),

            'account_receivable' => $this->whenLoaded('accountReceivable', function () {
                return [
                    'ar_id' => $this->accountReceivable->ar_id,
                    'ar_number' => $this->accountReceivable->ar_number,
                    'description' => $this->accountReceivable->description
                ];
            }),

            'processor' => $this->whenLoaded('processor', function () {
                return [
                    'user_id' => $this->processor->user_id,
                    'name' => $this->processor->name
                ];
            })
        ];
    }

    private function getPaymentMethodLabel()
    {
        $labels = [
            'CASH' => 'Efectivo',
            'TRANSFER' => 'Transferencia',
            'CHECK' => 'Cheque',
            'CARD' => 'Tarjeta',
            'YAPE' => 'Yape',
            'PLIN' => 'Plin',
            'OTHER' => 'Otro'
        ];

        return $labels[$this->payment_method] ?? $this->payment_method;
    }
}
