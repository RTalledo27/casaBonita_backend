<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Commission;
use Modules\Sales\Models\PaymentSchedule;

class DeleteAllContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:delete-all {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all contracts and their related data (commissions, payment schedules)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Confirmar antes de proceder
        if (!$this->option('force')) {
            if (!$this->confirm('⚠️  This will DELETE ALL CONTRACTS and related data. Are you sure?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->info('🗑️  Starting deletion process...');

        DB::beginTransaction();

        try {
            // 1. Contar registros antes de eliminar
            $contractsCount = DB::table('contracts')->count();
            $commissionsCount = DB::table('commissions')->count();
            $paymentSchedulesCount = DB::table('payment_schedules')->count();

            $this->info("Found:");
            $this->line("  - {$contractsCount} contracts");
            $this->line("  - {$commissionsCount} commissions");
            $this->line("  - {$paymentSchedulesCount} payment schedules");

            // 2. Eliminar comisiones
            $this->info('Deleting commissions...');
            $deleted = DB::table('commissions')->delete();
            $this->line("  ✅ Deleted {$deleted} commissions");

            // 3. Eliminar cronogramas de pago
            $this->info('Deleting payment schedules...');
            $deleted = DB::table('payment_schedules')->delete();
            $this->line("  ✅ Deleted {$deleted} payment schedules");

            // 4. Eliminar contratos
            $this->info('Deleting contracts...');
            $deleted = DB::table('contracts')->delete();
            $this->line("  ✅ Deleted {$deleted} contracts");

            DB::commit();

            $this->info('');
            $this->info('✅ All contracts and related data deleted successfully!');
            
            Log::info('[DeleteAllContracts] All contracts deleted', [
                'contracts' => $contractsCount,
                'commissions' => $commissionsCount,
                'payment_schedules' => $paymentSchedulesCount
            ]);

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            
            $this->error('❌ Error deleting contracts: ' . $e->getMessage());
            Log::error('[DeleteAllContracts] Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 1;
        }
    }
}
