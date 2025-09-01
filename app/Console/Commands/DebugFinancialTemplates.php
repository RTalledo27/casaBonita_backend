<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;
use Modules\Sales\Models\Contract;

class DebugFinancialTemplates extends Command
{
    protected $signature = 'debug:financial-templates';
    protected $description = 'Debug financial templates and contract values';

    public function handle()
    {
        $this->info('=== DEBUG: Template Financiero durante Importación ===');
        $this->newLine();

        // 1. Verificar lotes con templates financieros
        $this->info('1. Lotes con templates financieros:');
        $lotsWithTemplates = Lot::with('financialTemplate')
            ->whereHas('financialTemplate')
            ->get();

        $this->info("Total lotes con templates: {$lotsWithTemplates->count()}");
        $this->newLine();

        foreach ($lotsWithTemplates->take(5) as $lot) {
            $template = $lot->financialTemplate;
            $this->line("Lote: {$lot->lot_number} (Manzana: {$lot->block})");
            $this->line("  - Template ID: {$template->id}");
            $this->line("  - Precio Lista: {$template->precio_lista}");
            $this->line("  - Precio Venta: {$template->precio_venta}");
            $this->line("  - Cuota Inicial: {$template->cuota_inicial}");
            $this->line("  - Interest Rate: {$template->interest_rate}");
            $this->newLine();
        }

        // 2. Simular búsqueda de lote como en el import
        $this->info("\n2. Simulando búsqueda de lote (lote 1, manzana A):");
        
        $testLot = Lot::with(['manzana', 'financialTemplate'])
                      ->where('num_lot', 1)
                      ->whereHas('manzana', function($q) {
                          $q->where('name', 'A');
                      })
                      ->first();

        if ($testLot) {
            $this->info("✓ Lote encontrado: ID {$testLot->lot_id}");
            $this->line("  - Precio del lote: {$testLot->price}");
            
            $financialTemplate = $testLot->financialTemplate;
            if ($financialTemplate) {
                $this->info("✓ Template financiero encontrado: ID {$financialTemplate->id}");
                $this->line("  - Precio Lista: {$financialTemplate->precio_lista}");
                $this->line("  - Precio Venta: {$financialTemplate->precio_venta}");
                $this->line("  - Cuota Inicial: {$financialTemplate->cuota_inicial}");
                $this->line("  - Interest Rate: {$financialTemplate->interest_rate}");
                
                // Simular la lógica de createDirectContract
                $totalPrice = $financialTemplate->precio_venta ?? $financialTemplate->precio_lista ?? $testLot->price ?? 0;
                $downPayment = $financialTemplate->cuota_inicial ?? 0;
                $interestRate = $financialTemplate->interest_rate ?? 0;
                
                $this->newLine();
                $this->info('VALORES CALCULADOS (como en createDirectContract):');
                $this->line("  - Total Price: {$totalPrice}");
                $this->line("  - Down Payment: {$downPayment}");
                $this->line("  - Interest Rate: {$interestRate}");
                
            } else {
                $this->error('✗ NO se encontró template financiero para este lote');
                $this->line('Se usarían valores por defecto:');
                $defaultPrice = $testLot->price ?? 100000;
                $this->line("  - Total Price: {$defaultPrice}");
                $this->line("  - Down Payment: " . ($defaultPrice * 0.20));
            }
        } else {
            $this->error('✗ Lote NO encontrado');
        }

        $this->newLine();

        // 3. Verificar contratos recientes y sus valores
        $this->info('3. Contratos recientes y sus valores financieros:');
        $recentContracts = Contract::with(['lot', 'lot.financialTemplate'])
            ->orderBy('contract_id', 'desc')
            ->limit(3)
            ->get();

        foreach ($recentContracts as $contract) {
            $this->line("Contrato ID: {$contract->contract_id}");
            $this->line("  - Lote: {$contract->lot->lot_number} (Manzana: {$contract->lot->block})");
            $this->line("  - Total Price (contrato): {$contract->total_price}");
            $this->line("  - Down Payment (contrato): {$contract->down_payment}");
            $this->line("  - Interest Rate (contrato): {$contract->interest_rate}");
            
            if ($contract->lot->financialTemplate) {
                $template = $contract->lot->financialTemplate;
                $this->line("  - Precio Venta (template): {$template->precio_venta}");
                $this->line("  - Cuota Inicial (template): {$template->cuota_inicial}");
                $this->line("  - Interest Rate (template): {$template->interest_rate}");
                
                // Verificar si coinciden
                $priceMatch = $contract->total_price == $template->precio_venta;
                $downMatch = $contract->down_payment == $template->cuota_inicial;
                $rateMatch = $contract->interest_rate == $template->interest_rate;
                
                $this->line('COINCIDENCIAS:');
                $this->line('  - Precio: ' . ($priceMatch ? '✓' : '✗'));
                $this->line('  - Cuota Inicial: ' . ($downMatch ? '✓' : '✗'));
                $this->line('  - Interest Rate: ' . ($rateMatch ? '✓' : '✗'));
            } else {
                $this->line('  - Sin template financiero');
            }
            $this->newLine();
        }

        $this->info('=== FIN DEBUG ===');
        return 0;
    }
}