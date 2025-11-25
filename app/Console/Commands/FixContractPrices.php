<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Sales\Models\Contract;
use Illuminate\Support\Facades\DB;

class FixContractPrices extends Command
{
    protected $signature = 'fix:contract-prices';
    protected $description = 'Corrige los precios de los contratos usando la data JSON de Logicware (base_price, unit_price, total_price)';

    public function handle()
    {
        $this->info('Iniciando corrección de precios de contratos...');

        $contracts = Contract::whereNotNull('logicware_data')->get();
        $count = 0;
        $errors = 0;

        $this->output->progressStart($contracts->count());

        foreach ($contracts as $contract) {
            try {
                $data = $contract->logicware_data;
                
                if (is_string($data)) {
                    $data = json_decode($data, true);
                }

                if (!is_array($data)) {
                    continue;
                }

                // Buscar la unidad correcta en el array de unidades
                // Asumimos que la primera unidad es la que corresponde al lote del contrato
                // O buscamos por unitNumber si tuviéramos el código del lote a mano, pero logicware_data es el documento completo
                
                $units = $data['units'] ?? [];
                if (empty($units)) {
                    continue;
                }

                $unit = $units[0]; // Tomamos la primera unidad

                // Extraer precios del JSON
                $basePrice = $this->parseNumeric($unit['basePrice'] ?? 0);
                $unitPrice = $this->parseNumeric($unit['unitPrice'] ?? $unit['price'] ?? 0);
                $discount = $this->parseNumeric($unit['discount'] ?? 0);
                $total = $this->parseNumeric($unit['total'] ?? 0);

                // Si unitPrice es 0 pero tenemos basePrice, asumimos que unitPrice = basePrice (si no hubo negociación especial)
                // O si unitPrice es 0, intentamos calcularlo: total + discount
                if ($unitPrice <= 0) {
                    $unitPrice = $total + $discount;
                }

                // Actualizar contrato
                $contract->base_price = $basePrice > 0 ? $basePrice : null;
                $contract->unit_price = $unitPrice > 0 ? $unitPrice : null;
                
                // CORRECCIÓN CRÍTICA: total_price debe ser el total real (con descuento), no el base
                // Antes guardábamos basePrice en total_price. Ahora guardamos total.
                $contract->total_price = $total > 0 ? $total : $contract->total_price;
                
                $contract->save();
                $count++;

            } catch (\Exception $e) {
                $errors++;
                $this->error("Error en contrato {$contract->contract_number}: " . $e->getMessage());
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
        $this->info("Proceso finalizado. {$count} contratos actualizados. {$errors} errores.");
    }

    private function parseNumeric($value)
    {
        if (is_string($value)) {
            return (float) preg_replace('/[^0-9.]/', '', $value);
        }
        return (float) $value;
    }
}
