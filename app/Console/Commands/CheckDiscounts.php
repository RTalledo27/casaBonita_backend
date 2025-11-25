<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Commission;

class CheckDiscounts extends Command
{
    protected $signature = 'check:discounts';
    protected $description = 'Verifica descuentos y precios de contratos para conciliación de comisiones';

    public function handle()
    {
        $this->info('Verificando descuentos y precios de contratos vigentes...');

        $contracts = Contract::with(['reservation.lot.financialTemplate', 'advisor'])
            ->where('status', 'vigente')
            ->whereNotNull('advisor_id')
            ->where('discount', '>', 0)
            ->take(20)
            ->get();

        $output = "Contrato | Lote | Base | Unit (Venta) | Desc. | Total (Final) | Dif\n";
        $output .= "--------------------------------------------------------------------------------\n";

        foreach ($contracts as $contract) {
            $lotName = $contract->getLotName() ?? 'N/A';
            
            // Verificamos consistencia: Unit - Desc = Total?
            // Si unit_price es null, usamos total + discount como proxy para la validación
            $unitPrice = $contract->unit_price ?? ($contract->total_price + $contract->discount);
            $calculatedTotal = $unitPrice - $contract->discount;
            $diff = $calculatedTotal - $contract->total_price;

            $output .= sprintf(
                "%s | %s | %s | %s | %s | %s | %s\n",
                $contract->contract_number,
                str_pad($lotName, 10),
                number_format($contract->base_price, 2),
                number_format($contract->unit_price, 2),
                number_format($contract->discount, 2),
                number_format($contract->total_price, 2),
                number_format($diff, 2)
            );
        }

        file_put_contents(storage_path('logs/discounts_check.txt'), $output);
        $this->info('Reporte generado en storage/logs/discounts_check.txt');
    }
}
