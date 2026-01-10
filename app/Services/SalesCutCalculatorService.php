<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesCutCalculatorService
{
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
            ->leftJoin('clients as cl', function($join) {
                $join->on('c.client_id', '=', 'cl.client_id')
                     ->orOn('r.client_id', '=', 'cl.client_id');
            })
            ->leftJoin('lots as l', function($join) {
                $join->on('c.lot_id', '=', 'l.lot_id')
                     ->orOn('r.lot_id', '=', 'l.lot_id');
            })
            ->leftJoin('manzanas as m', 'l.manzana_id', '=', 'm.manzana_id')
            ->whereBetween('c.sign_date', [$startDate, $endDate])
            ->select(
                'c.contract_id',
                'c.total_price',
                'c.down_payment',
                'c.advisor_id',
                'c.sign_date',
                DB::raw('CONCAT(COALESCE(cl.first_name, ""), " ", COALESCE(cl.last_name, "")) as client_name'),
                DB::raw('CONCAT(COALESCE(m.name, ""), " - Lote ", COALESCE(l.num_lot, "")) as lot_name')
            )
            ->get();

        $revenue = $contracts->sum('total_price') ?? 0;
        $downPayments = $contracts->sum('down_payment') ?? 0;

        return [
            'count' => $contracts->count(),
            'revenue' => $revenue,
            'down_payments' => $downPayments,
            'contracts' => $contracts,
        ];
    }

    /**
     * Calcular pagos del período
     */
    private function calculatePayments(string $startDate, string $endDate): array
    {
        $payments = DB::table('payment_schedules')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('status', 'paid')
            ->select('schedule_id', 'contract_id', 'amount', 'payment_date', 'payment_method', 'installment_number')
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
     * Calcular comisiones (3% de las ventas)
     */
    private function calculateCommissions($contracts): float
    {
        $totalSales = $contracts->sum('total_price') ?? 0;
        return round($totalSales * 0.03, 2);
    }

    /**
     * Calcular saldos de caja y banco
     */
    private function calculateBalances(string $startDate, string $endDate): array
    {
        $cashBalance = DB::table('payment_schedules')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('status', 'paid')
            ->where('payment_method', 'cash')
            ->sum('amount') ?? 0;

        $bankBalance = DB::table('payment_schedules')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('status', 'paid')
            ->whereIn('payment_method', ['bank_transfer', 'card'])
            ->sum('amount') ?? 0;

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
                $advisor = DB::table('employees')->where('employee_id', $advisorId)->first();
                
                return [
                    'advisor_id' => $advisorId,
                    'advisor_name' => $advisor ? $advisor->first_name . ' ' . $advisor->last_name : 'Sin asignar',
                    'sales_count' => $advisorContracts->count(),
                    'total_amount' => $advisorContracts->sum('total_price'),
                    'commission' => round($advisorContracts->sum('total_price') * 0.03, 2),
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
