<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\PaymentSchedule;

class CheckImportStatus extends Command
{
    protected $signature = 'check:import-status';
    protected $description = 'Check contract and payment schedule counts';

    public function handle()
    {
        $contractsCount = Contract::count();
        $schedulesCount = PaymentSchedule::count();
        
        $this->info("Contracts: {$contractsCount}");
        $this->info("Payment Schedules: {$schedulesCount}");
        
        if ($contractsCount > 0) {
            $contract = Contract::first();
            $contractSchedules = PaymentSchedule::where('contract_id', $contract->contract_id)->count();
            
            $this->info("\nSample Contract:");
            $this->line("  ID: {$contract->contract_id}");
            $this->line("  Number: {$contract->contract_number}");
            $this->line("  Schedules: {$contractSchedules}");
        }
        
        return 0;
    }
}
