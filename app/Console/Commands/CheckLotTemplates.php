<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;

class CheckLotTemplates extends Command
{
    protected $signature = 'check:lot-templates';
    protected $description = 'Check lots with and without financial templates';

    public function handle()
    {
        $this->info('=== VERIFICACIÓN DE TEMPLATES FINANCIEROS ===');
        
        $totalLots = Lot::count();
        $lotsWithTemplate = Lot::whereHas('financialTemplate')->count();
        $lotsWithoutTemplate = Lot::whereDoesntHave('financialTemplate')->count();
        
        $this->info("Total de lotes: {$totalLots}");
        $this->info("Lotes con template: {$lotsWithTemplate}");
        $this->info("Lotes sin template: {$lotsWithoutTemplate}");
        
        if ($lotsWithoutTemplate > 0) {
            $this->warn("\n=== PRIMEROS 10 LOTES SIN TEMPLATE ===");
            $lotsWithoutTemplateData = Lot::whereDoesntHave('financialTemplate')
                ->with('manzana')
                ->take(10)
                ->get(['lot_id', 'num_lot', 'manzana_id', 'price']);
                
            foreach ($lotsWithoutTemplateData as $lot) {
                $manzanaName = $lot->manzana ? $lot->manzana->name : 'N/A';
                $this->line("Lote ID: {$lot->lot_id}, Num: {$lot->num_lot}, Manzana: {$manzanaName}, Precio: {$lot->price}");
            }
        }
        
        // Verificar algunos lotes específicos que podrían estar siendo buscados
        $this->info("\n=== VERIFICACIÓN DE LOTES ESPECÍFICOS ===");
        $testLots = [
            ['num_lot' => 1, 'manzana' => 'A'],
            ['num_lot' => 2, 'manzana' => 'A'],
            ['num_lot' => 3, 'manzana' => 'A']
        ];
        
        foreach ($testLots as $testLot) {
            $lot = Lot::whereHas('manzana', function($query) use ($testLot) {
                $query->where('name', $testLot['manzana']);
            })
            ->where('num_lot', $testLot['num_lot'])
            ->with(['financialTemplate', 'manzana'])
            ->first();
            
            if ($lot) {
                $hasTemplate = $lot->financialTemplate ? 'SÍ' : 'NO';
                $manzanaName = $lot->manzana ? $lot->manzana->name : 'N/A';
                $this->line("Lote {$lot->num_lot}, Manzana {$manzanaName}: Template = {$hasTemplate}");
                
                if ($lot->financialTemplate) {
                    $template = $lot->financialTemplate;
                    $this->line("  - Precio Lista: {$template->precio_lista}");
                    $this->line("  - Precio Venta: {$template->precio_venta}");
                    $this->line("  - Cuota Inicial: {$template->cuota_inicial}");
                    $this->line("  - Interest Rate: {$template->interest_rate}");
                }
            } else {
                $this->error("Lote {$testLot['num_lot']}, Manzana {$testLot['manzana']}: NO ENCONTRADO");
            }
        }
        
        return 0;
    }
}