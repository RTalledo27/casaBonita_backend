<?php

namespace App\Services;

use App\Models\SalesCut;
use App\Models\SalesCutItem;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\PaymentSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SalesCutService
{
    /**
     * Crear corte diario automático
     */
    public function createDailyCut(?string $date = null): SalesCut
    {
        $cutDate = $date ? Carbon::parse($date) : now();
        
        Log::info('[SalesCut] Creando corte diario', ['date' => $cutDate->toDateString()]);

        // Verificar si ya existe un corte para esta fecha
        $existingCut = SalesCut::where('cut_date', $cutDate->toDateString())
            ->where('cut_type', 'daily')
            ->first();

        if ($existingCut) {
            Log::info('[SalesCut] Ya existe corte para esta fecha', ['cut_id' => $existingCut->cut_id]);
            return $existingCut;
        }

        return DB::transaction(function () use ($cutDate) {
            // Crear el corte
            $cut = SalesCut::create([
                'cut_date' => $cutDate->toDateString(),
                'cut_type' => 'daily',
                'status' => 'open',
            ]);

            // Procesar ventas del día
            $this->processSales($cut, $cutDate);

            // Procesar pagos recibidos
            $this->processPayments($cut, $cutDate);

            // Calcular comisiones
            $this->processCommissions($cut, $cutDate);

            // Actualizar totales
            $this->updateCutTotals($cut);

            Log::info('[SalesCut] Corte diario creado exitosamente', [
                'cut_id' => $cut->cut_id,
                'total_sales' => $cut->total_sales_count,
                'total_payments' => $cut->total_payments_count,
                'revenue' => $cut->total_revenue,
            ]);

            return $cut->fresh();
        });
    }

    /**
     * Procesar ventas del día
     */
    protected function processSales(SalesCut $cut, Carbon $date): void
    {
        $sales = Contract::whereDate('sign_date', $date->toDateString())
            ->where('status', 'vigente')
            ->with(['advisor', 'client', 'lot'])
            ->get();

        foreach ($sales as $sale) {
            SalesCutItem::create([
                'cut_id' => $cut->cut_id,
                'item_type' => 'sale',
                'contract_id' => $sale->contract_id,
                'employee_id' => $sale->advisor_id,
                'amount' => $sale->total_price,
                'commission' => $this->calculateCommission($sale),
                'description' => "Venta: {$sale->contract_number}",
                'metadata' => [
                    'client_name' => $sale->client ? $sale->client->first_name . ' ' . $sale->client->last_name : null,
                    'lot_number' => $sale->lot ? $sale->lot->num_lot : null,
                    'advisor_name' => $sale->advisor && $sale->advisor->user 
                        ? $sale->advisor->user->first_name . ' ' . $sale->advisor->user->last_name 
                        : null,
                ],
            ]);
        }

        Log::info('[SalesCut] Ventas procesadas', ['cut_id' => $cut->cut_id, 'count' => $sales->count()]);
    }

    /**
     * Procesar pagos recibidos del día
     */
    protected function processPayments(SalesCut $cut, Carbon $date): void
    {
        // Obtener cuotas pagadas en el día
        $payments = PaymentSchedule::whereDate('paid_date', $date->toDateString())
            ->where('status', 'pagada')
            ->with(['contract.client', 'contract.advisor'])
            ->get();

        foreach ($payments as $payment) {
            SalesCutItem::create([
                'cut_id' => $cut->cut_id,
                'item_type' => 'payment',
                'contract_id' => $payment->contract_id,
                'payment_schedule_id' => $payment->schedule_id,
                'employee_id' => $payment->contract->advisor_id ?? null,
                'amount' => $payment->pending_amount ?? $payment->amount,
                'payment_method' => $payment->payment_method ?? 'cash',
                'description' => "Pago de cuota #{$payment->installment_number}",
                'metadata' => [
                    'contract_number' => $payment->contract->contract_number ?? null,
                    'client_name' => $payment->contract->client 
                        ? $payment->contract->client->first_name . ' ' . $payment->contract->client->last_name 
                        : null,
                    'installment_number' => $payment->installment_number,
                    'installment_type' => $payment->type,
                ],
            ]);
        }

        Log::info('[SalesCut] Pagos procesados', ['cut_id' => $cut->cut_id, 'count' => $payments->count()]);
    }

    /**
     * Procesar comisiones generadas
     */
    protected function processCommissions(SalesCut $cut, Carbon $date): void
    {
        // Obtener comisiones de las ventas del día
        $salesItems = $cut->items()->sales()->get();

        foreach ($salesItems as $item) {
            if ($item->commission > 0 && $item->employee_id) {
                SalesCutItem::create([
                    'cut_id' => $cut->cut_id,
                    'item_type' => 'commission',
                    'contract_id' => $item->contract_id,
                    'employee_id' => $item->employee_id,
                    'amount' => 0,
                    'commission' => $item->commission,
                    'description' => "Comisión por venta {$item->contract->contract_number}",
                    'metadata' => [
                        'commission_rate' => $this->getCommissionRate($item->contract),
                        'sale_amount' => $item->amount,
                    ],
                ]);
            }
        }

        Log::info('[SalesCut] Comisiones procesadas', ['cut_id' => $cut->cut_id]);
    }

    /**
     * Actualizar totales del corte
     */
    protected function updateCutTotals(SalesCut $cut): void
    {
        $items = $cut->items;

        $salesItems = $items->where('item_type', 'sale');
        $paymentItems = $items->where('item_type', 'payment');
        $commissionItems = $items->where('item_type', 'commission');

        // Calcular balances por método de pago
        $cashBalance = $paymentItems->where('payment_method', 'cash')->sum('amount');
        $bankBalance = $paymentItems->whereIn('payment_method', ['bank_transfer', 'credit_card', 'debit_card'])->sum('amount');

        $cut->update([
            'total_sales_count' => $salesItems->count(),
            'total_revenue' => $salesItems->sum('amount'),
            'total_down_payments' => $salesItems->sum(function ($item) {
                return $item->contract ? $item->contract->down_payment : 0;
            }),
            'total_payments_count' => $paymentItems->count(),
            'total_payments_received' => $paymentItems->sum('amount'),
            'paid_installments_count' => $paymentItems->count(),
            'total_commissions' => $commissionItems->sum('commission') + $salesItems->sum('commission'),
            'cash_balance' => $cashBalance,
            'bank_balance' => $bankBalance,
            'summary_data' => [
                'sales_by_advisor' => $this->getSalesByAdvisor($cut),
                'payments_by_method' => $this->getPaymentsByMethod($cut),
                'top_sales' => $this->getTopSales($cut),
            ],
        ]);

        Log::info('[SalesCut] Totales actualizados', ['cut_id' => $cut->cut_id]);
    }

    /**
     * Calcular comisión de una venta
     */
    protected function calculateCommission(Contract $contract): float
    {
        // Tasa de comisión por defecto: 3% del total
        $commissionRate = 0.03;
        
        // Aquí podrías tener lógica más compleja basada en:
        // - Nivel del asesor
        // - Monto de la venta
        // - Tipo de producto
        // - Bonos especiales
        
        return $contract->total_price * $commissionRate;
    }

    /**
     * Obtener tasa de comisión
     */
    protected function getCommissionRate(Contract $contract): float
    {
        return 0.03; // 3%
    }

    /**
     * Obtener ventas por asesor
     */
    protected function getSalesByAdvisor(SalesCut $cut): array
    {
        return $cut->items()
            ->sales()
            ->with('employee.user')
            ->get()
            ->groupBy('employee_id')
            ->map(function ($items) {
                $employee = $items->first()->employee;
                return [
                    'advisor_name' => $employee && $employee->user 
                        ? $employee->user->first_name . ' ' . $employee->user->last_name 
                        : 'Sin asesor',
                    'sales_count' => $items->count(),
                    'total_amount' => $items->sum('amount'),
                    'total_commission' => $items->sum('commission'),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Obtener pagos por método
     */
    protected function getPaymentsByMethod(SalesCut $cut): array
    {
        return $cut->items()
            ->payments()
            ->get()
            ->groupBy('payment_method')
            ->map(function ($items, $method) {
                return [
                    'method' => $method,
                    'count' => $items->count(),
                    'total' => $items->sum('amount'),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Obtener top 5 ventas del día
     */
    protected function getTopSales(SalesCut $cut): array
    {
        return $cut->items()
            ->sales()
            ->with('contract.client')
            ->orderBy('amount', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'contract_number' => $item->contract->contract_number ?? 'N/A',
                    'client_name' => $item->metadata['client_name'] ?? 'N/A',
                    'amount' => $item->amount,
                    'advisor_name' => $item->metadata['advisor_name'] ?? 'N/A',
                ];
            })
            ->toArray();
    }

    /**
     * Cerrar corte manualmente
     */
    public function closeCut(int $cutId, int $userId): SalesCut
    {
        $cut = SalesCut::findOrFail($cutId);

        if ($cut->isClosed()) {
            throw new \Exception('El corte ya está cerrado');
        }

        // Recalcular totales antes de cerrar
        $this->updateCutTotals($cut);

        $cut->close($userId);

        Log::info('[SalesCut] Corte cerrado manualmente', [
            'cut_id' => $cutId,
            'closed_by' => $userId,
        ]);

        return $cut->fresh();
    }

    /**
     * Agregar venta al corte actual (real-time)
     */
    public function addSaleToCurrentCut(Contract $contract): void
    {
        $cut = $this->getTodayCut();
        
        if (!$cut || $cut->status !== 'open') {
            Log::warning('[SalesCut] No hay corte abierto para agregar venta', [
                'contract_id' => $contract->contract_id
            ]);
            return;
        }

        try {
            // Crear item de venta
            SalesCutItem::create([
                'cut_id' => $cut->cut_id,
                'item_type' => 'sale',
                'contract_id' => $contract->contract_id,
                'employee_id' => $contract->advisor_id,
                'amount' => $contract->total_price,
                'commission' => $this->calculateCommission($contract),
                'description' => "Venta: {$contract->contract_number}",
                'metadata' => [
                    'client_name' => $contract->client ? $contract->client->first_name . ' ' . $contract->client->last_name : null,
                    'lot_number' => $contract->lot ? $contract->lot->num_lot : null,
                    'advisor_name' => $contract->advisor && $contract->advisor->user 
                        ? $contract->advisor->user->first_name . ' ' . $contract->advisor->user->last_name 
                        : null,
                ],
            ]);

            // Crear item de comisión si aplica
            $commission = $this->calculateCommission($contract);
            if ($commission > 0 && $contract->advisor_id) {
                SalesCutItem::create([
                    'cut_id' => $cut->cut_id,
                    'item_type' => 'commission',
                    'contract_id' => $contract->contract_id,
                    'employee_id' => $contract->advisor_id,
                    'amount' => 0,
                    'commission' => $commission,
                    'description' => "Comisión por venta {$contract->contract_number}",
                    'metadata' => [
                        'commission_rate' => $this->getCommissionRate($contract),
                        'sale_amount' => $contract->total_price,
                    ],
                ]);
            }

            // Recalcular totales
            $this->updateCutTotals($cut);

            Log::info('[SalesCut] Venta agregada al corte en tiempo real', [
                'cut_id' => $cut->cut_id,
                'contract_id' => $contract->contract_id,
                'amount' => $contract->total_price,
                'commission' => $commission,
            ]);
        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al agregar venta al corte', [
                'error' => $e->getMessage(),
                'contract_id' => $contract->contract_id,
            ]);
        }
    }

    /**
     * Agregar pago al corte actual (real-time)
     */
    public function addPaymentToCurrentCut(PaymentSchedule $payment): void
    {
        $cut = $this->getTodayCut();
        
        if (!$cut || $cut->status !== 'open') {
            Log::warning('[SalesCut] No hay corte abierto para agregar pago', [
                'schedule_id' => $payment->schedule_id
            ]);
            return;
        }

        try {
            // Crear item de pago
            SalesCutItem::create([
                'cut_id' => $cut->cut_id,
                'item_type' => 'payment',
                'contract_id' => $payment->contract_id,
                'payment_schedule_id' => $payment->schedule_id,
                'employee_id' => $payment->contract->advisor_id ?? null,
                'amount' => $payment->amount_paid ?? $payment->amount,
                'payment_method' => $payment->payment_method ?? 'cash',
                'description' => "Pago de cuota #{$payment->installment_number}",
                'metadata' => [
                    'contract_number' => $payment->contract->contract_number ?? null,
                    'client_name' => $payment->contract->client 
                        ? $payment->contract->client->first_name . ' ' . $payment->contract->client->last_name 
                        : null,
                    'installment_number' => $payment->installment_number,
                    'installment_type' => $payment->type,
                ],
            ]);

            // Recalcular totales
            $this->updateCutTotals($cut);

            Log::info('[SalesCut] Pago agregado al corte en tiempo real', [
                'cut_id' => $cut->cut_id,
                'schedule_id' => $payment->schedule_id,
                'amount' => $payment->amount_paid ?? $payment->amount,
            ]);
        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al agregar pago al corte', [
                'error' => $e->getMessage(),
                'schedule_id' => $payment->schedule_id,
            ]);
        }
    }

    /**
     * Obtener corte del día actual
     */
    public function getTodayCut(): ?SalesCut
    {
        return SalesCut::today()->with('items')->first();
    }

    /**
     * Obtener estadísticas del mes actual
     */
    public function getMonthlyStats(): array
    {
        $cuts = SalesCut::thisMonth()->get();

        return [
            'total_sales' => $cuts->sum('total_sales_count'),
            'total_revenue' => $cuts->sum('total_revenue'),
            'total_payments' => $cuts->sum('total_payments_received'),
            'total_commissions' => $cuts->sum('total_commissions'),
            'daily_average' => [
                'sales' => $cuts->avg('total_sales_count'),
                'revenue' => $cuts->avg('total_revenue'),
                'payments' => $cuts->avg('total_payments_received'),
            ],
            'cuts_count' => $cuts->count(),
            'closed_cuts' => $cuts->where('status', '!=', 'open')->count(),
        ];
    }
}
