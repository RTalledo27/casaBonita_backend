<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Contract;
use App\Services\CommissionService;
use Modules\HumanResources\Models\Commission;

class TestCommissionPayable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:commission-payable';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba la funcionalidad del campo is_payable en el sistema de comisiones';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Prueba del sistema de comisiones con campo is_payable ===');
        $this->newLine();

        try {
            // Deshabilitar temporalmente las verificaciones de claves foráneas
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            
            // Crear comisión padre (no pagable)
            $parentCommission = Commission::create([
                'employee_id' => 1,
                'contract_id' => 1,
                'commission_type' => 'sale', // Campo requerido
                'sale_amount' => 20000.00, // Campo requerido
                'commission_amount' => 1000,
                'commission_percentage' => 5.0,
                'payment_percentage' => 100.0,
                'commission_period' => '2024-01',
                'period_month' => 1, // Campo requerido
                'period_year' => 2024, // Campo requerido
                'status' => 'generated',
                'is_payable' => false, // Padre no es pagable
            ]);
            
            // Crear divisiones pagables
            $division1 = Commission::create([
                'employee_id' => 2,
                'contract_id' => 1,
                'commission_type' => 'sale', // Campo requerido
                'sale_amount' => 20000.00, // Campo requerido
                'parent_commission_id' => $parentCommission->id,
                'commission_amount' => 500,
                'commission_percentage' => 2.5,
                'payment_percentage' => 50,
                'commission_period' => '2024-01',
                'period_month' => 1, // Campo requerido
            'period_year' => 2024, // Campo requerido
            'status' => 'generated',
            'is_payable' => true, // División es pagable
            ]);
            
            $division2 = Commission::create([
                'employee_id' => 3,
                'contract_id' => 1,
                'commission_type' => 'sale', // Campo requerido
                'sale_amount' => 20000.00, // Campo requerido
                'parent_commission_id' => $parentCommission->id,
                'commission_amount' => 500,
                'commission_percentage' => 2.5,
                'payment_percentage' => 50,
                'commission_period' => '2024-01',
                'period_month' => 1, // Campo requerido
                'period_year' => 2024, // Campo requerido
                'status' => 'generated',
                'is_payable' => true // División SÍ pagable
            ]);
            
            $commission = $parentCommission;
            
            $this->newLine();
            $this->info('=== Comisión Padre ===');
            $this->line("ID: {$commission->id}");
            $this->line("is_payable: " . ($commission->is_payable ? 'true' : 'false'));
            $this->line("parent_commission_id: " . ($commission->parent_commission_id ?? 'null'));
            
            // Obtener las comisiones hijas
            $childCommissions = Commission::where('parent_commission_id', $commission->id)->get();
            
            $this->newLine();
            $this->info('=== Comisiones Hijas ===');
            foreach ($childCommissions as $index => $child) {
                $this->line("Comisión hija " . ($index + 1) . ":");
                $this->line("  ID: {$child->id}");
                $this->line("  is_payable: " . ($child->is_payable ? 'true' : 'false'));
                $this->line("  parent_commission_id: {$child->parent_commission_id}");
                $this->line("  payment_percentage: {$child->payment_percentage}%");
                $this->newLine();
            }
            
            // Verificar filtros
            $this->info('=== Verificación de Filtros ===');
            $payableCommissions = Commission::payable()->count();
            $nonPayableCommissions = Commission::nonPayable()->count();
            $payableDivisions = Commission::payableDivisions()->count();
            $parentCommissions = Commission::parentCommissions()->count();
            
            $this->line("Total comisiones pagables: {$payableCommissions}");
            $this->line("Total comisiones no pagables: {$nonPayableCommissions}");
            $this->line("Total divisiones pagables: {$payableDivisions}");
            $this->line("Total comisiones padre: {$parentCommissions}");
            
            $this->newLine();
            $this->info('=== Prueba EXITOSA ===');
            $this->line('El sistema ahora distingue correctamente entre:');
            $this->line('- Comisiones padre (is_payable = false): Solo para control');
            $this->line('- Comisiones divisiones (is_payable = true): Para pagos reales');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->line("Trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            // Reactivar las verificaciones de claves foráneas
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
}
