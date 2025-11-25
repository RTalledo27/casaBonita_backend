<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\Services\CommissionService;

class RegenerateCommissions extends Command
{
    protected $signature = 'commissions:regenerate';
    protected $description = 'Regenera todas las comisiones usando la nueva lógica con total_price';

    public function handle(CommissionService $commissionService)
    {
        $this->info('🔄 Regenerando comisiones con nueva lógica (total_price)...');
        
        // Obtener todos los períodos únicos de contratos
        $periods = Contract::selectRaw('MONTH(sign_date) as month, YEAR(sign_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();
        
        if ($periods->isEmpty()) {
            $this->warn('No hay contratos para procesar');
            return 0;
        }
        
        $totalCommissions = 0;
        
        foreach ($periods as $period) {
            $this->line("Procesando período: {$period->year}-{$period->month}");
            
            $commissions = $commissionService->processCommissionsForPeriod(
                $period->month,
                $period->year
            );
            
            $count = count($commissions);
            $totalCommissions += $count;
            
            $this->info("  ✅ {$count} comisiones generadas");
        }
        
        $this->info("🎉 Total de comisiones regeneradas: {$totalCommissions}");
        
        return 0;
    }
}
