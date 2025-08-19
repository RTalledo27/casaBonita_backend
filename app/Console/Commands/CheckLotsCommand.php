<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\Manzana;

class CheckLotsCommand extends Command
{
    protected $signature = 'check:lots';
    protected $description = 'Check available lots and manzanas with financial templates';

    public function handle()
    {
        $this->info('=== MANZANAS DISPONIBLES ===');
        $manzanas = Manzana::select('manzana_id', 'name')->get();
        foreach ($manzanas as $manzana) {
            $this->line("ID: {$manzana->manzana_id}, Nombre: {$manzana->name}");
        }

        $this->newLine();
        $this->info('=== LOTES CON TEMPLATE FINANCIERO ===');
        $lots = Lot::whereHas('financialTemplate')
            ->with('manzana:manzana_id,name')
            ->select('lot_id', 'num_lot', 'manzana_id')
            ->take(5)
            ->get();
            
        foreach ($lots as $lot) {
            $this->line("Lote: {$lot->num_lot}, Manzana: {$lot->manzana->name} (ID: {$lot->manzana_id})");
        }

        return 0;
    }
}