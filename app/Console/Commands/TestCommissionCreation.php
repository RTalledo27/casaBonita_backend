<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\HumanResources\Services\CommissionService;
use Modules\Sales\Models\Contract;
use Carbon\Carbon;

class TestCommissionCreation extends Command
{
    protected $signature = 'test:commission-creation {--month=} {--year=}';
    protected $description = 'Test commission creation flow with splits';

    public function handle()
    {
        $month = $this->option('month') ?: now()->month;
        $year = $this->option('year') ?: now()->year;
        
        $this->info('=== PRUEBA DE CREACIÓN DE COMISIONES ===');
        $this->line("Período: {$month}/{$year}");
        
        // Verificar si hay contratos en el período
        $contracts = Contract::with('advisor')
            ->whereMonth('sign_date', $month)
            ->whereYear('sign_date', $year)
            ->get();
            
        $this->line("\nContratos encontrados en el período: {$contracts->count()}");
        
        if ($contracts->count() === 0) {
            $this->warn('No hay contratos en el período especificado.');
            $this->info('Buscando contratos en otros períodos...');
            
            $recentContracts = Contract::with('advisor')
                ->orderBy('sign_date', 'desc')
                ->take(5)
                ->get();
                
            if ($recentContracts->count() > 0) {
                $this->line("\nContratos recientes encontrados:");
                foreach ($recentContracts as $contract) {
                    $signDate = Carbon::parse($contract->sign_date);
                    $advisorName = isset($contract->advisor->user->name) ? $contract->advisor->user->name : 'N/A';
                    $this->line("   - Contrato {$contract->contract_id}: {$signDate->format('Y-m-d')} - Asesor: {$advisorName}");
                }
                
                $latestContract = $recentContracts->first();
                $latestDate = Carbon::parse($latestContract->sign_date);
                $month = $latestDate->month;
                $year = $latestDate->year;
                
                $this->info("\nUsando período del contrato más reciente: {$month}/{$year}");
            } else {
                $this->error('No hay contratos en el sistema.');
                return 1;
            }
        }
        
        // Contar comisiones antes del procesamiento
        $commissionService = app(CommissionService::class);
        $repo = app(\Modules\HumanResources\Repositories\CommissionRepository::class);
        
        $beforeCount = $repo->getAll([])->count();
        $beforeParent = $repo->getAll([])->where('is_payable', false)->count();
        $beforeChild = $repo->getAll([])->where('is_payable', true)->count();
        
        $this->line("\nComisiones ANTES del procesamiento:");
        $this->line("   Total: {$beforeCount}");
        $this->line("   Padre: {$beforeParent}");
        $this->line("   Hijas: {$beforeChild}");
        
        // Procesar comisiones
        $this->info("\nProcesando comisiones para {$month}/{$year}...");
        
        try {
            $result = $commissionService->processCommissionsForPeriod($month, $year);
            
            $this->info('✅ Procesamiento completado exitosamente');
            $this->line("Comisiones creadas: " . count($result));
            
        } catch (\Exception $e) {
            $this->error('❌ Error durante el procesamiento:');
            $this->error($e->getMessage());
            return 1;
        }
        
        // Contar comisiones después del procesamiento
        $afterCount = $repo->getAll([])->count();
        $afterParent = $repo->getAll([])->where('is_payable', false)->count();
        $afterChild = $repo->getAll([])->where('is_payable', true)->count();
        
        $this->line("\nComisiones DESPUÉS del procesamiento:");
        $this->line("   Total: {$afterCount}");
        $this->line("   Padre: {$afterParent}");
        $this->line("   Hijas: {$afterChild}");
        
        // Análisis de resultados
        $newCommissions = $afterCount - $beforeCount;
        $newParent = $afterParent - $beforeParent;
        $newChild = $afterChild - $beforeChild;
        
        $this->line("\nNuevas comisiones creadas:");
        $this->line("   Total nuevas: {$newCommissions}");
        $this->line("   Nuevas padre: {$newParent}");
        $this->line("   Nuevas hijas: {$newChild}");
        
        // Verificaciones
        if ($newParent > 0 && $newChild > 0) {
            $this->info('\n✅ ÉXITO: Se crearon tanto comisiones padre como hijas');
        } elseif ($newParent > 0 && $newChild === 0) {
            $this->warn('\n⚠️  ADVERTENCIA: Solo se crearon comisiones padre, no hay divisiones');
        } elseif ($newCommissions === 0) {
            $this->warn('\n⚠️  No se crearon nuevas comisiones (posiblemente ya existen para este período)');
        }
        
        // Mostrar ejemplos de comisiones recientes
        $recentCommissions = $repo->getAll([])
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->take(5);
            
        if ($recentCommissions->count() > 0) {
            $this->line("\nEjemplos de comisiones del período {$month}/{$year}:");
            foreach ($recentCommissions as $commission) {
                $type = $commission->is_payable ? 'HIJA' : 'PADRE';
                $parent = $commission->parent_commission_id ? " (Padre: {$commission->parent_commission_id})" : '';
                $this->line("   - [{$type}] ID: {$commission->commission_id}, Empleado: {$commission->employee->user->name}, Monto: {$commission->amount}{$parent}");
            }
        }
        
        $this->info('\n=== PRUEBA COMPLETADA ===');
        
        return 0;
    }
}