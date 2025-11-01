<?php

namespace Modules\Reports\app\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentScheduleProjectionService
{
    /**
     * Get payment schedule projection data for export
     * Returns a matrix with contracts and their payments by date
     */
    public function getPaymentScheduleProjection(int $monthsAhead = 12): array
    {
        // Get all pending payment schedules for the next X months
        $endDate = Carbon::now()->addMonths($monthsAhead);
        
        $schedules = DB::table('payment_schedules as ps')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->join('clients as cl', 'c.client_id', '=', 'cl.client_id')
            ->join('lots as l', 'c.lot_id', '=', 'l.lot_id')
            ->join('manzanas as m', 'l.manzana_id', '=', 'm.manzana_id')
            ->select(
                'ps.schedule_id',
                'ps.contract_id',
                'c.contract_number',
                DB::raw('CONCAT(cl.first_name, " ", cl.last_name) as client_name'),
                DB::raw('CONCAT(m.name, "-", l.num_lot) as lot_number'),
                'ps.installment_number',
                'ps.due_date',
                'ps.amount',
                'ps.status'
            )
            ->whereIn('ps.status', ['pendiente', 'vencido'])
            ->where('ps.due_date', '<=', $endDate)
            ->whereNull('ps.deleted_at')
            ->orderBy('c.contract_id')
            ->orderBy('ps.due_date')
            ->get();

        if ($schedules->isEmpty()) {
            return $this->getEmptyStructure();
        }

        // Get unique payment dates (only dates where there are actual payments)
        $paymentDates = $schedules->pluck('due_date')->unique()->sort()->values()->toArray();
        
        // Group schedules by contract
        $contractGroups = $schedules->groupBy('contract_id');
        
        // Build the export data
        $exportData = [];
        
        // Header row 1: Month names
        $monthRow = ['N° VENTA', 'NOMBRE DE CLIENTE', 'N° DE LOTE'];
        $currentMonth = null;
        $monthColspan = [];
        
        foreach ($paymentDates as $date) {
            $monthYear = Carbon::parse($date)->format('F Y');
            if ($monthYear !== $currentMonth) {
                $currentMonth = $monthYear;
                $monthColspan[$currentMonth] = 0;
            }
            $monthColspan[$currentMonth]++;
            $monthRow[] = strtoupper(Carbon::parse($date)->locale('es')->translatedFormat('F'));
        }
        
        // Header row 2: Actual dates
        $dateRow = ['', '', ''];
        foreach ($paymentDates as $date) {
            $dateRow[] = Carbon::parse($date)->format('d/m/Y');
        }
        
        $exportData[] = $monthRow;
        $exportData[] = $dateRow;
        
        // Data rows: One row per contract
        foreach ($contractGroups as $contractId => $payments) {
            $firstPayment = $payments->first();
            $row = [
                $firstPayment->contract_number ?? 'N/A',
                $firstPayment->client_name ?? 'Sin nombre',
                $firstPayment->lot_number ?? 'N/A'
            ];
            
            // Create a map of due_date => amount for this contract
            $paymentMap = $payments->pluck('amount', 'due_date')->toArray();
            
            // Fill in the payment amounts for each date column
            foreach ($paymentDates as $date) {
                $row[] = isset($paymentMap[$date]) ? 'S/ ' . number_format($paymentMap[$date], 2) : '';
            }
            
            $exportData[] = $row;
        }
        
        // Add summary row with totals per date
        $totalRow = ['', 'TOTAL POR DÍA', ''];
        foreach ($paymentDates as $date) {
            $dailyTotal = $schedules->where('due_date', $date)->sum('amount');
            $totalRow[] = 'S/ ' . number_format($dailyTotal, 2);
        }
        $exportData[] = $totalRow;
        
        return [
            'Cronograma de Cobros' => $exportData
        ];
    }
    
    /**
     * Get empty structure when no data
     */
    private function getEmptyStructure(): array
    {
        return [
            'Cronograma de Cobros' => [
                ['N° VENTA', 'NOMBRE DE CLIENTE', 'N° DE LOTE', 'MENSAJE'],
                ['', '', '', 'No hay pagos programados en el período seleccionado']
            ]
        ];
    }
    
    /**
     * Get summary statistics
     */
    public function getPaymentSummary(int $monthsAhead = 12): array
    {
        $endDate = Carbon::now()->addMonths($monthsAhead);
        
        $summary = DB::table('payment_schedules')
            ->whereIn('status', ['pendiente', 'vencido'])
            ->where('due_date', '<=', $endDate)
            ->whereNull('deleted_at')
            ->selectRaw('
                COUNT(*) as total_payments,
                SUM(amount) as total_amount,
                COUNT(DISTINCT contract_id) as total_contracts
            ')
            ->first();
        
        return [
            'total_payments' => $summary->total_payments ?? 0,
            'total_amount' => $summary->total_amount ?? 0,
            'total_contracts' => $summary->total_contracts ?? 0,
            'period_months' => $monthsAhead,
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d')
        ];
    }
}
