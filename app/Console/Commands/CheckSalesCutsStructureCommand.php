<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckSalesCutsStructureCommand extends Command
{
    protected $signature = 'cuts:check-structure';
    protected $description = 'Verificar estructura de la tabla sales_cuts y mostrar datos';

    public function handle()
    {
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('   📊 ESTRUCTURA DE SALES_CUTS');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        // Obtener columnas de la tabla
        $columns = Schema::getColumnListing('sales_cuts');
        
        $this->info('📋 Columnas de la tabla:');
        foreach ($columns as $column) {
            $type = Schema::getColumnType('sales_cuts', $column);
            $this->line("   • $column ($type)");
        }
        
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('   📊 DATOS ACTUALES');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();
        
        // Obtener todos los registros
        $cuts = DB::table('sales_cuts')->orderBy('cut_date', 'desc')->get();
        
        $this->info("Total de registros: " . $cuts->count());
        $this->newLine();
        
        foreach ($cuts as $cut) {
            $this->line("📅 Fecha: {$cut->cut_date}");
            foreach ((array) $cut as $key => $value) {
                if ($key !== 'cut_date') {
                    $this->line("   $key: " . ($value ?? 'NULL'));
                }
            }
            $this->newLine();
        }

        return 0;
    }
}
