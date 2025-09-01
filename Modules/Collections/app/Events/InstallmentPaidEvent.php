<?php

namespace Modules\Collections\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Collections\Models\CustomerPayment;
use Illuminate\Support\Str;

class InstallmentPaidEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $id;
    public CustomerPayment $payment;
    public string $installmentType;
    public array $eventData;

    /**
     * Create a new event instance.
     */
    public function __construct(
        CustomerPayment $payment, 
        string $installmentType, 
        array $additionalData = []
    ) {
        $this->id = (string) Str::uuid();
        $this->payment = $payment;
        $this->installmentType = $installmentType;
        $this->eventData = array_merge([
            'payment_id' => $payment->payment_id,
            'client_id' => $payment->client_id,
            'ar_id' => $payment->ar_id,
            'contract_id' => $payment->accountReceivable?->contract_id,
            'amount' => $payment->amount,
            'payment_date' => $payment->payment_date,
            'installment_type' => $installmentType,
            'event_id' => $this->id,
            'triggered_at' => now()->toISOString()
        ], $additionalData);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('commission-verification'),
            new PrivateChannel('payment-events.' . $this->payment->client_id),
            new PrivateChannel('contract-events.' . $this->eventData['contract_id'])
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->id,
            'event_type' => 'installment_paid',
            'payment_id' => $this->payment->payment_id,
            'client_id' => $this->payment->client_id,
            'contract_id' => $this->eventData['contract_id'],
            'installment_type' => $this->installmentType,
            'amount' => $this->payment->amount,
            'payment_date' => $this->payment->payment_date,
            'message' => "Cuota {$this->installmentType} pagada por el cliente",
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'installment.paid';
    }

    /**
     * Determine if this event should broadcast.
     */
    public function shouldBroadcast(): bool
    {
        return in_array($this->installmentType, ['first', 'second']) && 
               $this->eventData['contract_id'] !== null;
    }

    /**
     * Get event data for logging and processing.
     */
    public function getEventData(): array
    {
        return $this->eventData;
    }

    /**
     * Get contract ID from the payment.
     */
    public function getContractId(): ?int
    {
        return $this->eventData['contract_id'];
    }

    /**
     * Check if this installment affects commissions.
     */
    public function affectsCommissions(): bool
    {
        return in_array($this->installmentType, ['first', 'second']) && 
               $this->getContractId() !== null;
    }

    /**
     * Get a summary of the event for logging.
     */
    public function getSummary(): string
    {
        return "Installment payment event: {$this->installmentType} installment of {$this->payment->amount} " .
               "for contract {$this->getContractId()} by client {$this->payment->client_id}";
    }
}