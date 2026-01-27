<?php

namespace Modules\Sales\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return  [
            'payment_id'      => $this->payment_id,
            'schedule_id'     => $this->schedule_id,
            'journal_entry_id' => $this->journal_entry_id,
            'payment_date'    => $this->payment_date,
            'amount'          => $this->amount,
            'method'          => $this->method,
            'reference'       => $this->reference,
            'schedule'        => new PaymentScheduleResource($this->whenLoaded('schedule')),
            //'journal_entry'   => new JournalEntryResource($this->whenLoaded('journalEntry')),
        ];
    }
}
