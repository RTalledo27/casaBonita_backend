<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesCutCalculatorService
{
    private const LOGICWARE_SOLD_STATUSES = ['vendido', 'venta', 'sale', 'sold'];
    private const LOGICWARE_RESERVED_STATUSES = ['reservado', 'reserva', 'separacion', 'separación', 'proforma', 'bloqueado', 'blocked'];

    /**
     * Calcular corte para un período específico
     * 
     * @param Carbon $startDate Fecha inicio
     * @param Carbon $endDate Fecha fin
     * @param bool $includeDetails Si incluir detalles de top sales y por asesor
     * @return array Datos calculados del corte
     */
    public function calculateCut(Carbon $startDate, Carbon $endDate, bool $includeDetails = true): array
    {
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');

        // Calcular ventas (contratos firmados en el período)
        $salesData = $this->calculateSales($startDateStr, $endDateStr);
        
        // Calcular pagos (pagos realizados en el período)
        $paymentsData = $this->calculatePayments($startDateStr, $endDateStr);
        
        // Calcular comisiones
        $commissions = $this->calculateCommissions($salesData['contracts']);
        
        // Calcular saldos
        $balances = $this->calculateBalances($startDateStr, $endDateStr);
        
        // Generar summary data
        $summaryData = $includeDetails ? $this->generateSummaryData(
            $salesData['contracts'],
            $paymentsData['payments'],
            $startDateStr,
            $endDateStr
        ) : [
            'top_sales' => [],
            'sales_by_advisor' => [],
            'payments_by_method' => []
        ];

        return [
            'period' => [
                'start' => $startDateStr,
                'end' => $endDateStr,
                'days' => $startDate->diffInDays($endDate) + 1,
            ],
            'total_sales_count' => $salesData['count'],
            'total_revenue' => $salesData['revenue'],
            'total_down_payments' => $salesData['down_payments'],
            'reserved_sales_count' => $salesData['reserved_count'] ?? 0,
            'reserved_revenue' => $salesData['reserved_revenue'] ?? 0,
            'total_payments_count' => $paymentsData['count'],
            'total_payments_received' => $paymentsData['amount'],
            'paid_installments_count' => $paymentsData['installments'],
            'total_commissions' => $commissions,
            'cash_balance' => $balances['cash'],
            'bank_balance' => $balances['bank'],
            'summary_data' => $summaryData,
        ];
    }

    /**
     * Calcular ventas del período
     */
    private function calculateSales(string $startDate, string $endDate): array
    {
        $contracts = DB::table('contracts as c')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->leftJoin('clients as cl', function ($join) {
                $join->on('cl.client_id', '=', DB::raw('COALESCE(c.client_id, r.client_id)'));
            })
            ->leftJoin('lots as l', function ($join) {
                $join->on('l.lot_id', '=', DB::raw('COALESCE(c.lot_id, r.lot_id)'));
            })
            ->leftJoin('manzanas as m', 'l.manzana_id', '=', 'm.manzana_id')
            ->whereBetween('c.sign_date', [$startDate, $endDate])
            ->where('c.status', 'vigente')
            ->select(
                'c.contract_id',
                'c.total_price',
                'c.down_payment',
                'c.advisor_id',
                'c.sign_date',
                'c.source',
                'l.status as lot_status',
                DB::raw('CONCAT(COALESCE(cl.first_name, ""), " ", COALESCE(cl.last_name, "")) as client_name'),
                DB::raw('CONCAT(COALESCE(m.name, ""), " - Lote ", COALESCE(l.num_lot, "")) as lot_name'),
                DB::raw("LOWER(TRIM(COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(c.logicware_data,'$.units[0].status')),
                    JSON_UNQUOTE(JSON_EXTRACT(c.logicware_data,'$.units[0].state')),
                    JSON_UNQUOTE(JSON_EXTRACT(c.logicware_data,'$.unit_status')),
                    JSON_UNQUOTE(JSON_EXTRACT(c.logicware_data,'$.unit.status')),
                    JSON_UNQUOTE(JSON_EXTRACT(c.logicware_data,'$.unit.state')),
                    JSON_UNQUOTE(JSON_EXTRACT(c.logicware_data,'$.status')),
                    JSON_UNQUOTE(JSON_EXTRACT(c.logicware_data,'$.state')),
                    l.status,
                    ''
                ))) as logicware_status")
            )
            ->get();

        $classified = $contracts->map(function ($c) {
            $lotStatus = strtolower(trim((string) ($c->lot_status ?? '')));
            $saleStage = $lotStatus === 'vendido' ? 'vendido' : 'reservado';

            $c->sale_stage = $saleStage;
            return $c;
        });

        $soldContracts = $classified->where('sale_stage', 'vendido')->values();
        $reservedContracts = $classified->where('sale_stage', 'reservado')->values();

        $revenue = $soldContracts->sum('total_price') ?? 0;
        $downPayments = $soldContracts->sum('down_payment') ?? 0;
        $reservedRevenue = $reservedContracts->sum('total_price') ?? 0;

        return [
            'count' => $soldContracts->count(),
            'revenue' => $revenue,
            'down_payments' => $downPayments,
            'reserved_count' => $reservedContracts->count(),
            'reserved_revenue' => $reservedRevenue,
            'contracts' => $soldContracts,
            'reserved_contracts' => $reservedContracts,
        ];
    }

    /**
     * Calcular pagos del período
     */
    private function calculatePayments(string $startDate, string $endDate): array
    {
        $payments = DB::table('payments as p')
            ->join('payment_schedules as ps', 'p.schedule_id', '=', 'ps.schedule_id')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->leftJoin('lots as l', function ($join) {
                $join->on('l.lot_id', '=', DB::raw('COALESCE(c.lot_id, r.lot_id)'));
            })
            ->whereBetween('p.payment_date', [$startDate, $endDate])
            ->where('c.status', 'vigente')
            ->where('l.status', 'vendido')
            ->select(
                'p.payment_id',
                'ps.contract_id',
                'p.amount',
                'p.payment_date',
                'p.method as payment_method',
                'ps.installment_number'
            )
            ->get();

        $amount = $payments->sum('amount') ?? 0;
        $installments = $payments->count();

        return [
            'count' => $payments->count(),
            'amount' => $amount,
            'installments' => $installments,
            'payments' => $payments,
        ];
    }

    /**
     * Calcular comisiones del período desde la tabla commissions
     * Usa las comisiones REALES que ya fueron calculadas por el sistema
     */
    private function calculateCommissions($contracts): float
    {
        if ($contracts->isEmpty()) {
            return 0;
        }

        // Obtener IDs de contratos
        $contractIds = $contracts->pluck('contract_id')->toArray();

        // Sumar comisiones reales de la tabla commissions para estos contratos
        $totalCommissions = DB::table('commissions')
            ->whereIn('contract_id', $contractIds)
            ->whereNull('parent_commission_id') // Solo comisiones padre (no divididas)
            ->where('status', '!=', 'cancelled')
            ->sum('commission_amount') ?? 0;

        return round($totalCommissions, 2);
    }

    /**
     * Calcular saldos de caja y banco
     */
    private function calculateBalances(string $startDate, string $endDate): array
    {
        $cashBalance = DB::table('payments as p')
            ->join('payment_schedules as ps', 'p.schedule_id', '=', 'ps.schedule_id')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->leftJoin('lots as l', function ($join) {
                $join->on('l.lot_id', '=', DB::raw('COALESCE(c.lot_id, r.lot_id)'));
            })
            ->whereBetween('p.payment_date', [$startDate, $endDate])
            ->where('c.status', 'vigente')
            ->where('l.status', 'vendido')
            ->where('p.method', 'efectivo')
            ->sum('p.amount') ?? 0;

        $bankBalance = DB::table('payments as p')
            ->join('payment_schedules as ps', 'p.schedule_id', '=', 'ps.schedule_id')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->leftJoin('lots as l', function ($join) {
                $join->on('l.lot_id', '=', DB::raw('COALESCE(c.lot_id, r.lot_id)'));
            })
            ->whereBetween('p.payment_date', [$startDate, $endDate])
            ->where('c.status', 'vigente')
            ->where('l.status', 'vendido')
            ->whereIn('p.method', ['transferencia', 'tarjeta', 'yape', 'plin'])
            ->sum('p.amount') ?? 0;

        return [
            'cash' => $cashBalance,
            'bank' => $bankBalance,
        ];
    }

    /**
     * Generar datos de resumen (top ventas, por asesor, etc)
     */
    private function generateSummaryData($contracts, $payments, string $startDate, string $endDate): array
    {
        // Top 10 ventas
        $topSales = $contracts->sortByDesc('total_price')
            ->take(10)
            ->map(function($contract) {
                return [
                    'contract_id' => $contract->contract_id,
                    'client_name' => $contract->client_name,
                    'lot_name' => $contract->lot_name,
                    'amount' => $contract->total_price,
                    'date' => $contract->sign_date,
                ];
            })->values()->all();

        // Ventas por asesor
        $salesByAdvisor = $contracts->groupBy('advisor_id')
            ->map(function($advisorContracts, $advisorId) {
                $advisor = DB::table('employees as e')
                    ->join('users as u', 'e.user_id', '=', 'u.user_id')
                    ->where('e.employee_id', $advisorId)
                    ->select('u.first_name', 'u.last_name')
                    ->first();
                
                // Obtener comisiones reales de estos contratos
                $contractIds = $advisorContracts->pluck('contract_id')->toArray();
                $realCommission = DB::table('commissions')
                    ->whereIn('contract_id', $contractIds)
                    ->where('employee_id', $advisorId)
                    ->whereNull('parent_commission_id') // Solo comisiones padre
                    ->where('status', '!=', 'cancelled')
                    ->sum('commission_amount') ?? 0;
                
                return [
                    'advisor_id' => $advisorId,
                    'advisor_name' => $advisor ? $advisor->first_name . ' ' . $advisor->last_name : 'Sin asignar',
                    'sales_count' => $advisorContracts->count(),
                    'total_amount' => $advisorContracts->sum('total_price'),
                    'commission' => round($realCommission, 2),
                ];
            })
            ->sortByDesc('total_amount')
            ->values()
            ->all();

        // Pagos por método
        $paymentsByMethod = $payments->groupBy('payment_method')
            ->map(function($methodPayments, $method) {
                return [
                    'method' => $method ?? 'No especificado',
                    'count' => $methodPayments->count(),
                    'amount' => $methodPayments->sum('amount'),
                ];
            })
            ->values()
            ->all();

        return [
            'top_sales' => $topSales,
            'sales_by_advisor' => $salesByAdvisor,
            'payments_by_method' => $paymentsByMethod,
        ];
    }

    /**
     * Determinar el tipo de corte basado en el rango de fechas
     */
    public function determineCutType(Carbon $startDate, Carbon $endDate): string
    {
        $days = $startDate->diffInDays($endDate) + 1;

        if ($days === 1) {
            return 'daily';
        } elseif ($days === 7) {
            return 'weekly';
        } elseif ($days >= 28 && $days <= 31) {
            return 'monthly';
        } elseif ($days >= 90 && $days <= 92) {
            return 'quarterly';
        } elseif ($days >= 365 && $days <= 366) {
            return 'yearly';
        }

        return 'custom';
    }
}
