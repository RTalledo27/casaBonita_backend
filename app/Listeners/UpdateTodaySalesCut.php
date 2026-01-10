<?php

namespace App\Listeners;

use App\Events\ContractCreated;
use App\Events\PaymentRecorded;
use App\Services\SalesCutService;
use Illuminate\Support\Facades\Log;

class UpdateTodaySalesCut
{
    protected SalesCutService $salesCutService;

    public function __construct(SalesCutService $salesCutService)
    {
        $this->salesCutService = $salesCutService;
    }

    /**
     * Manejar evento de contrato creado
     */
    public function handleContractCreated(ContractCreated $event): void
    {
        try {
            $cut = $this->salesCutService->getTodayCut();
            
            if (!$cut || $cut->status !== 'open') {
                return;
            }

            // Agregar venta al corte
            $this->salesCutService->addSaleToCurrentCut($event->contract);
            
            Log::info('[SalesCut] Venta agregada al corte', [
                'contract_id' => $event->contract->contract_id,
                'cut_id' => $cut->cut_id,
            ]);
        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al agregar venta al corte', [
                'error' => $e->getMessage(),
                'contract_id' => $event->contract->contract_id ?? null,
            ]);
        }
    }

    /**
     * Manejar evento de pago registrado
     */
    public function handlePaymentRecorded(PaymentRecorded $event): void
    {
        try {
            $cut = $this->salesCutService->getTodayCut();
            
            if (!$cut || $cut->status !== 'open') {
                return;
            }

            // Agregar pago al corte
            $this->salesCutService->addPaymentToCurrentCut($event->payment);
            
            Log::info('[SalesCut] Pago agregado al corte', [
                'payment_schedule_id' => $event->payment->payment_schedule_id,
                'cut_id' => $cut->cut_id,
            ]);
        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al agregar pago al corte', [
                'error' => $e->getMessage(),
                'payment_schedule_id' => $event->payment->payment_schedule_id ?? null,
            ]);
        }
    }
}
