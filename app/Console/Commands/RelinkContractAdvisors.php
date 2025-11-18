<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Employee;

class RelinkContractAdvisors extends Command
{
    protected $signature = 'contracts:relink-advisors {--dry-run : Solo mostrar coincidencias sin guardar}';
    protected $description = 'Re-vincula asesores a contratos usando score-based matching mejorado';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('🔄 Iniciando re-vinculación de asesores...');
        if ($dryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se guardarán cambios');
        }
        $this->newLine();
        
        // Obtener contratos sin asesor
        $contracts = Contract::whereNull('advisor_id')->get();
        $total = $contracts->count();
        
        if ($total === 0) {
            $this->info('✅ Todos los contratos ya tienen asesor asignado');
            return Command::SUCCESS;
        }
        
        $this->info("📋 Contratos sin asesor: {$total}");
        $this->newLine();
        
        // Obtener todos los empleados para matching
        $allEmployees = Employee::whereHas('user')->with('user')->get();
        
        $matched = 0;
        $notMatched = 0;
        $progressBar = $this->output->createProgressBar($total);
        
        foreach ($contracts as $contract) {
            // Buscar nombre del asesor en el contract_number o en datos relacionados
            // Por ahora, intentaremos match con todos los empleados
            
            $advisor = $this->findBestAdvisorMatch($contract, $allEmployees);
            
            if ($advisor) {
                $matched++;
                
                if (!$dryRun) {
                    $contract->advisor_id = $advisor->employee_id;
                    $contract->save();
                }
                
                $this->newLine();
                $this->line("✅ Contrato {$contract->contract_number} → {$advisor->user->first_name} {$advisor->user->last_name}");
            } else {
                $notMatched++;
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("✅ Coincidencias encontradas: {$matched}");
        $this->error("❌ Sin coincidencia: {$notMatched}");
        $this->info("📊 Tasa de éxito: " . round(($matched/$total)*100, 1) . "%");
        
        if ($dryRun) {
            $this->newLine();
            $this->warn('💡 Ejecuta sin --dry-run para guardar los cambios');
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Encuentra el mejor asesor usando score-based matching
     */
    private function findBestAdvisorMatch(Contract $contract, $allEmployees)
    {
        // Por ahora, retornar null ya que necesitamos datos del asesor en el contrato
        // Esto se mejorará cuando tengamos esa información
        return null;
    }
}
