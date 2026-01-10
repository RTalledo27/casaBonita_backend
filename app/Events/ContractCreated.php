<?php

namespace App\Events;

use Modules\Sales\Models\Contract;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContractCreated
{
    use Dispatchable, SerializesModels;

    public Contract $contract;

    public function __construct(Contract $contract)
    {
        $this->contract = $contract;
    }
}
