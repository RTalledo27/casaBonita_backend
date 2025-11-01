<?php

namespace Modules\Collections\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Modules\Sales\Models\Contract;
use Modules\Collections\Services\PaymentScheduleGenerationService;
use Modules\Collections\Models\PaymentSchedule;
use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Models\CustomerPayment;

class CollectionsDashboardController extends Controller
{
    protected PaymentScheduleGenerationService $scheduleGenerationService;

    public function __construct(PaymentScheduleGenerationService $scheduleGenerationService)
    {
        $this->scheduleGenerationService = $scheduleGenerationService;
    }

    /**
     * Obtiene datos generales del dashboard de cobranzas
     */
    public function getDashboard(): JsonResponse
    {
        try {
            $currentDate = Carbon::now();
            
            // Métricas principales
            $scheduleMetrics = $this->getScheduleMetrics($currentDate);
            $arMetrics = $this->getAccountReceivableMetrics($currentDate);
            $paymentMetrics = $this->getPaymentMetrics($currentDate);
            $contractMetrics = $this->getContractMetrics();
            
            // Cronogramas recién creados (últimos 5 contratos con cronogramas, agrupados por contrato)
            // Maneja tanto contratos directos (client_id, lot_id) como contratos desde reserva (reservation_id)
            $recentCreatedSchedules = DB::table('payment_schedules as ps')
                ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
                ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
                ->leftJoin('clients as cl_res', 'r.client_id', '=', 'cl_res.client_id')
                ->leftJoin('lots as l_res', 'r.lot_id', '=', 'l_res.lot_id')
                ->leftJoin('clients as cl_dir', 'c.client_id', '=', 'cl_dir.client_id')
                ->leftJoin('lots as l_dir', 'c.lot_id', '=', 'l_dir.lot_id')
                ->leftJoin('manzanas as m_res', 'l_res.manzana_id', '=', 'm_res.manzana_id')
                ->leftJoin('manzanas as m_dir', 'l_dir.manzana_id', '=', 'm_dir.manzana_id')
                ->select(
                    'ps.contract_id',
                    'c.contract_number',
                    DB::raw('COALESCE(
                        CONCAT(cl_res.first_name, " ", cl_res.last_name),
                        CONCAT(cl_dir.first_name, " ", cl_dir.last_name)
                    ) as client_name'),
                    DB::raw('COALESCE(m_res.name, m_dir.name) as manzana_name'),
                    DB::raw('COALESCE(l_res.num_lot, l_dir.num_lot) as num_lot'),
                    DB::raw('COUNT(*) as total_schedules'),
                    DB::raw('MAX(ps.schedule_id) as latest_schedule_id')
                )
                ->groupBy(
                    'ps.contract_id', 
                    'c.contract_number', 
                    'cl_res.first_name', 'cl_res.last_name',
                    'cl_dir.first_name', 'cl_dir.last_name',
                    'm_res.name', 'm_dir.name',
                    'l_res.num_lot', 'l_dir.num_lot'
                )
                ->orderBy('latest_schedule_id', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($scheduleGroup) {
                    return [
                        'contract_id' => $scheduleGroup->contract_id,
                        'contract_number' => $scheduleGroup->contract_number,
                        'client_name' => $scheduleGroup->client_name,
                        'lot_name' => $scheduleGroup->manzana_name . '-' . $scheduleGroup->num_lot,
                        'total_schedules' => $scheduleGroup->total_schedules,
                        'status' => 'Creado'
                    ];
                });

            // Cronogramas próximos (próximos 7 días)
            $upcomingSchedules = PaymentSchedule::with([
                'contract.reservation.client',
                'contract.reservation.lot.manzana'
            ])
            ->whereBetween('due_date', [$currentDate->format('Y-m-d'), $currentDate->copy()->addDays(7)->format('Y-m-d')])
            ->where('status', 'pendiente')
            ->orderBy('due_date', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($schedule) {
                return [
                    'schedule_id' => $schedule->schedule_id,
                    'contract_number' => $schedule->contract->contract_number,
                    'client_name' => $schedule->contract->reservation->client->full_name ?? 'N/A',
                    'due_date' => $schedule->due_date,
                    'amount' => $schedule->amount,
                    'status' => $schedule->status,
                    'days_until_due' => Carbon::parse($schedule->due_date)->diffInDays(Carbon::now())
                ];
            });
            
            // Cronogramas vencidos (últimos 5)
            $overdueSchedules = PaymentSchedule::with([
                'contract.reservation.client',
                'contract.reservation.lot.manzana'
            ])
            ->where('due_date', '<', $currentDate->format('Y-m-d'))
            ->where('status', 'pendiente')
            ->orderBy('due_date', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($schedule) use ($currentDate) {
                return [
                    'schedule_id' => $schedule->schedule_id,
                    'contract_number' => $schedule->contract->contract_number,
                    'client_name' => $schedule->contract->reservation->client->full_name ?? 'N/A',
                    'due_date' => $schedule->due_date,
                    'amount' => $schedule->amount,
                    'status' => $schedule->status,
                    'days_overdue' => $currentDate->diffInDays(Carbon::parse($schedule->due_date))
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Datos del dashboard obtenidos exitosamente',
                'data' => [
                    'metrics' => [
                        'schedules' => $scheduleMetrics,
                        'accounts_receivable' => $arMetrics,
                        'payments' => $paymentMetrics,
                        'contracts' => $contractMetrics
                    ],
                    'recent_created_schedules' => $recentCreatedSchedules,
                    'upcoming_schedules' => $upcomingSchedules,
                    'overdue_schedules' => $overdueSchedules,
                    'last_updated' => $currentDate->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo datos del dashboard: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtiene métricas generales del dashboard de cobranzas
     */
    public function getDashboardMetrics(): JsonResponse
    {
        try {
            $currentDate = Carbon::now();
            
            // Métricas de cronogramas de pagos
            $scheduleMetrics = $this->getScheduleMetrics($currentDate);
            
            // Métricas de cuentas por cobrar
            $arMetrics = $this->getAccountReceivableMetrics($currentDate);
            
            // Métricas de pagos de clientes
            $paymentMetrics = $this->getPaymentMetrics($currentDate);
            
            // Métricas de contratos
            $contractMetrics = $this->getContractMetrics();
            
            // Estadísticas de generación de cronogramas
            $generationStats = $this->scheduleGenerationService->getGenerationStats();

            return response()->json([
                'success' => true,
                'message' => 'Métricas del dashboard obtenidas exitosamente',
                'data' => [
                    'schedules' => $scheduleMetrics,
                    'accounts_receivable' => $arMetrics,
                    'payments' => $paymentMetrics,
                    'contracts' => $contractMetrics,
                    'generation_stats' => $generationStats,
                    'last_updated' => $currentDate->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo métricas del dashboard: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtiene cronogramas próximos a vencer
     */
    public function getUpcomingSchedules(Request $request): JsonResponse
    {
        try {
            $days = $request->input('days', 30);
            $limit = $request->input('limit', 50);
            $currentDate = Carbon::now();
            $futureDate = $currentDate->copy()->addDays($days);

            $upcomingSchedules = PaymentSchedule::with([
                'contract.reservation.client',
                'contract.reservation.lot.manzana'
            ])
            ->whereBetween('due_date', [$currentDate->format('Y-m-d'), $futureDate->format('Y-m-d')])
            ->where('status', 'pendiente')
            ->orderBy('due_date', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($schedule) {
                return [
                    'schedule_id' => $schedule->schedule_id,
                    'contract_number' => $schedule->contract->contract_number,
                    'client_name' => $schedule->contract->reservation->client->full_name ?? 'N/A',
                    'lot_info' => (
                        $schedule->contract->reservation->lot->manzana->name ?? 'N/A'
                    ) . '-' . ($schedule->contract->reservation->lot->num_lot ?? 'N/A'),
                    'due_date' => $schedule->due_date,
                    'amount' => $schedule->amount,
                    'days_until_due' => Carbon::parse($schedule->due_date)->diffInDays(Carbon::now()),
                    'status' => $schedule->status
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Cronogramas próximos obtenidos exitosamente',
                'data' => $upcomingSchedules
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo cronogramas próximos: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtiene cronogramas vencidos
     */
    public function getOverdueSchedules(Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', 50);
            $currentDate = Carbon::now();

            $overdueSchedules = PaymentSchedule::with([
                'contract.reservation.client',
                'contract.reservation.lot.manzana'
            ])
            ->where('due_date', '<', $currentDate->format('Y-m-d'))
            ->where('status', 'pendiente')
            ->orderBy('due_date', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($schedule) use ($currentDate) {
                return [
                    'schedule_id' => $schedule->schedule_id,
                    'contract_number' => $schedule->contract->contract_number,
                    'client_name' => $schedule->contract->reservation->client->full_name ?? 'N/A',
                    'lot_info' => (
                        $schedule->contract->reservation->lot->manzana->name ?? 'N/A'
                    ) . '-' . ($schedule->contract->reservation->lot->num_lot ?? 'N/A'),
                    'due_date' => $schedule->due_date,
                    'amount' => $schedule->amount,
                    'days_overdue' => $currentDate->diffInDays(Carbon::parse($schedule->due_date)),
                    'status' => $schedule->status
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Cronogramas vencidos obtenidos exitosamente',
                'data' => $overdueSchedules
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo cronogramas vencidos: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtiene resumen de cobranzas por período
     */
    public function getCollectionsSummary(Request $request): JsonResponse
    {
        try {
            $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

            // Pagos recibidos en el período
            $paymentsReceived = CustomerPayment::whereBetween('payment_date', [$startDate, $endDate])
                ->sum('amount');

            // Cronogramas que vencen en el período
            $schedulesDue = PaymentSchedule::whereBetween('due_date', [$startDate, $endDate])
                ->sum('amount');

            // Cronogramas pagados en el período
            $schedulesPaid = PaymentSchedule::whereBetween('due_date', [$startDate, $endDate])
                ->where('status', 'paid')
                ->sum('amount');

            // Eficiencia de cobranza
            $collectionEfficiency = $schedulesDue > 0 ? ($schedulesPaid / $schedulesDue) * 100 : 0;

            return response()->json([
                'success' => true,
                'message' => 'Resumen de cobranzas obtenido exitosamente',
                'data' => [
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'payments_received' => $paymentsReceived,
                    'schedules_due' => $schedulesDue,
                    'schedules_paid' => $schedulesPaid,
                    'collection_efficiency' => round($collectionEfficiency, 2),
                    'pending_amount' => $schedulesDue - $schedulesPaid
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo resumen de cobranzas: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtiene tendencias de cobranza por período
     */
    public function getTrends(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', 'monthly');
            $months = $request->input('months', 12);
            
            $trends = [];
            $currentDate = Carbon::now();
            
            for ($i = $months - 1; $i >= 0; $i--) {
                $date = $currentDate->copy()->subMonths($i);
                $startOfMonth = $date->startOfMonth()->format('Y-m-d');
                $endOfMonth = $date->endOfMonth()->format('Y-m-d');
                
                $totalAmount = PaymentSchedule::whereBetween('due_date', [$startOfMonth, $endOfMonth])
                    ->sum('amount');
                    
                $paidAmount = PaymentSchedule::whereBetween('due_date', [$startOfMonth, $endOfMonth])
                    ->where('status', 'pagado')
                    ->sum('amount');
                    
                $overdueAmount = PaymentSchedule::where('due_date', '<', $endOfMonth)
                    ->where('status', 'pendiente')
                    ->sum('amount');
                    
                $scheduleCount = PaymentSchedule::whereBetween('due_date', [$startOfMonth, $endOfMonth])
                    ->count();
                
                $trends[] = [
                    'month' => $date->format('M Y'),
                    'totalAmount' => $totalAmount,
                    'paidAmount' => $paidAmount,
                    'overdueAmount' => $overdueAmount,
                    'scheduleCount' => $scheduleCount
                ];
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Tendencias obtenidas exitosamente',
                'data' => $trends
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo tendencias: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Métricas de cronogramas de pagos
     */
    private function getScheduleMetrics(Carbon $currentDate): array
    {
        $totalSchedules = PaymentSchedule::count();
        $pendingSchedules = PaymentSchedule::where('status', 'pendiente')->count();
        $paidSchedules = PaymentSchedule::where('status', 'pagado')->count();
        $overdueSchedules = PaymentSchedule::where('due_date', '<', $currentDate->format('Y-m-d'))
            ->where('status', 'pendiente')
            ->count();

        $totalAmount = PaymentSchedule::sum('amount');
        $pendingAmount = PaymentSchedule::where('status', 'pendiente')->sum('amount');
        $paidAmount = PaymentSchedule::where('status', 'pagado')->sum('amount');
        $overdueAmount = PaymentSchedule::where('due_date', '<', $currentDate->format('Y-m-d'))
            ->where('status', 'pendiente')
            ->sum('amount');

        return [
            'total_schedules' => $totalSchedules,
            'pending_schedules' => $pendingSchedules,
            'paid_schedules' => $paidSchedules,
            'overdue_schedules' => $overdueSchedules,
            'total_amount' => $totalAmount,
            'pending_amount' => $pendingAmount,
            'paid_amount' => $paidAmount,
            'overdue_amount' => $overdueAmount
        ];
    }

    /**
     * Métricas de cuentas por cobrar
     */
    private function getAccountReceivableMetrics(Carbon $currentDate): array
    {
        $totalAR = AccountReceivable::count();
        $pendingAR = AccountReceivable::where('status', 'PENDING')->count();
        $paidAR = AccountReceivable::where('status', 'PAID')->count();
        
        $totalARAmount = AccountReceivable::sum('original_amount');
        $pendingARAmount = AccountReceivable::where('status', 'PENDING')->sum('outstanding_amount');
        $paidARAmount = AccountReceivable::where('status', 'PAID')->sum('original_amount');

        return [
            'total_ar' => $totalAR,
            'pending_ar' => $pendingAR,
            'paid_ar' => $paidAR,
            'total_ar_amount' => $totalARAmount,
            'pending_ar_amount' => $pendingARAmount,
            'paid_ar_amount' => $paidARAmount
        ];
    }

    /**
     * Métricas de pagos de clientes
     */
    private function getPaymentMetrics(Carbon $currentDate): array
    {
        $thisMonth = $currentDate->format('Y-m');
        $lastMonth = $currentDate->copy()->subMonth()->format('Y-m');

        $thisMonthPayments = CustomerPayment::whereRaw('DATE_FORMAT(payment_date, "%Y-%m") = ?', [$thisMonth])
            ->sum('amount');
        $lastMonthPayments = CustomerPayment::whereRaw('DATE_FORMAT(payment_date, "%Y-%m") = ?', [$lastMonth])
            ->sum('amount');

        $thisMonthCount = CustomerPayment::whereRaw('DATE_FORMAT(payment_date, "%Y-%m") = ?', [$thisMonth])
            ->count();
        $lastMonthCount = CustomerPayment::whereRaw('DATE_FORMAT(payment_date, "%Y-%m") = ?', [$lastMonth])
            ->count();

        return [
            'this_month_amount' => $thisMonthPayments,
            'last_month_amount' => $lastMonthPayments,
            'this_month_count' => $thisMonthCount,
            'last_month_count' => $lastMonthCount,
            'monthly_growth' => $lastMonthPayments > 0 
                ? (($thisMonthPayments - $lastMonthPayments) / $lastMonthPayments) * 100 
                : 0
        ];
    }

    /**
     * Métricas de contratos
     */
    private function getContractMetrics(): array
    {
        $totalContracts = Contract::count();
        $contractsWithSchedules = Contract::whereHas('paymentSchedules')->count();
        $contractsWithoutSchedules = $totalContracts - $contractsWithSchedules;

        return [
            'total_contracts' => $totalContracts,
            'contracts_with_schedules' => $contractsWithSchedules,
            'contracts_without_schedules' => $contractsWithoutSchedules,
            'schedule_coverage_percentage' => $totalContracts > 0 
                ? ($contractsWithSchedules / $totalContracts) * 100 
                : 0
        ];
    }
}