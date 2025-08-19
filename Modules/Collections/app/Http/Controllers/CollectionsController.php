<?php

namespace Modules\Collections\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\PaymentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CollectionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            // Obtener métricas del dashboard
            $totalContracts = Contract::count();
            $activeContracts = Contract::where('status', 'active')->count();
            
            // Cronogramas de pago
            $totalSchedules = PaymentSchedule::count();
            $paidSchedules = PaymentSchedule::where('status', 'pagado')->count();
            $pendingSchedules = PaymentSchedule::where('status', 'pendiente')->count();
            $overdueSchedules = PaymentSchedule::where('status', 'pendiente')
                ->where('due_date', '<', Carbon::now())
                ->count();
            
            // Montos
            $totalAmount = PaymentSchedule::sum('amount');
            $paidAmount = PaymentSchedule::where('status', 'pagado')->sum('amount');
            $pendingAmount = PaymentSchedule::where('status', 'pendiente')->sum('amount');
            $overdueAmount = PaymentSchedule::where('status', 'pendiente')
                ->where('due_date', '<', Carbon::now())
                ->sum('amount');
            
            // Cronogramas recientes
            $recentSchedules = PaymentSchedule::with(['contract.reservation.client', 'contract.reservation.lot', 'payments'])
                ->orderBy('schedule_id', 'desc')
                ->limit(10)
                ->get();
            
            // Próximos vencimientos
            $upcomingSchedules = PaymentSchedule::with(['contract.reservation.client', 'contract.reservation.lot', 'payments'])
                ->where('status', 'pendiente')
                ->where('due_date', '>=', Carbon::now())
                ->where('due_date', '<=', Carbon::now()->addDays(30))
                ->orderBy('due_date', 'asc')
                ->limit(10)
                ->get();
            
            $dashboardData = [
                'metrics' => [
                    'total_contracts' => $totalContracts,
                    'active_contracts' => $activeContracts,
                    'total_schedules' => $totalSchedules,
                    'paid_schedules' => $paidSchedules,
                    'pending_schedules' => $pendingSchedules,
                    'overdue_schedules' => $overdueSchedules,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'pending_amount' => $pendingAmount,
                    'overdue_amount' => $overdueAmount,
                ],
                'recent_schedules' => $recentSchedules,
                'upcoming_schedules' => $upcomingSchedules,
            ];
            
            return response()->json([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully',
                'data' => $dashboardData
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard data: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Create method not implemented'
        ], 501);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Store method not implemented'
        ], 501);
    }

    /**
     * Show the specified resource.
     */
    public function show($id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Collection resource found',
            'data' => [
                'id' => $id,
                'type' => 'collection'
            ]
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Edit method not implemented'
        ], 501);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Update method not implemented'
        ], 501);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Destroy method not implemented'
        ], 501);
    }
}
