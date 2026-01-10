<?php

namespace App\Events;

use Modules\Sales\Models\PaymentSchedule;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRecorded
{
    use Dispatchable, SerializesModels;

    public PaymentSchedule $payment;

    public function __construct(PaymentSchedule $payment)
    {
        $this->payment = $payment;
    }
}
