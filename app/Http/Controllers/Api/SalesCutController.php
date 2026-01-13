<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SalesCutService;
use App\Services\SalesCutCalculatorService;
use App\Models\SalesCut;
use App\Exports\SalesCutExport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalesCutController extends Controller
{
    protected SalesCutService $salesCutService;
    protected SalesCutCalculatorService $calculatorService;

    public function __construct(
        SalesCutService $salesCutService,
        SalesCutCalculatorService $calculatorService
    ) {
        $this->salesCutService = $salesCutService;
        $this->calculatorService = $calculatorService;
    }

    /**
     * Obtener todos los cortes con paginación
     * GET /api/v1/sales/cuts
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $status = $request->input('status');
            $type = $request->input('type');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $query = SalesCut::with(['closedBy', 'reviewedBy']);

            if ($status) {
                $query->where('status', $status);
            }

            if ($type) {
                $query->where('cut_type', $type);
            }

            if ($startDate) {
                $query->whereDate('cut_date', '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate('cut_date', '<=', $endDate);
            }

            $cuts = $query->orderBy('cut_date', 'desc')
                         ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $cuts,
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al obtener cortes', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cortes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener corte del día actual
     * GET /api/v1/sales/cuts/today
     */
    public function today(): JsonResponse
    {
        try {
            $cut = $this->salesCutService->getTodayCut();

            if (!$cut) {
                // Crear corte si no existe
                $cut = $this->salesCutService->createDailyCut();
            }

            return response()->json([
                'success' => true,
                'data' => $cut->load(['items.contract.client', 'items.employee.user', 'closedBy']),
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al obtener corte del día', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener corte del día',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener un corte específico
     * GET /api/v1/sales/cuts/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $cut = SalesCut::with([
                'items.contract.client',
                'items.contract.lot',
                'items.employee.user',
                'items.paymentSchedule',
                'closedBy',
                'reviewedBy',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $cut,
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al obtener corte', [
                'cut_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener corte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear corte diario manualmente
     * POST /api/v1/sales/cuts/create-daily
     */
    public function createDaily(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'date' => 'nullable|date',
            ]);

            $cut = $this->salesCutService->createDailyCut($validated['date'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Corte diario creado exitosamente',
                'data' => $cut->load('items'),
            ], 201);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al crear corte diario', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear corte diario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cerrar un corte
     * POST /api/v1/sales/cuts/{id}/close
     */
    public function close(int $id, Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $cut = $this->salesCutService->closeCut($id, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Corte cerrado exitosamente',
                'data' => $cut,
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al cerrar corte', [
                'cut_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cerrar corte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marcar corte como revisado
     * POST /api/v1/sales/cuts/{id}/review
     */
    public function review(int $id, Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $cut = SalesCut::findOrFail($id);

            if ($cut->status !== 'closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'El corte debe estar cerrado antes de ser revisado',
                ], 400);
            }

            $cut->markAsReviewed($userId);

            return response()->json([
                'success' => true,
                'message' => 'Corte revisado exitosamente',
                'data' => $cut->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al revisar corte', [
                'cut_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al revisar corte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas del mes
     * GET /api/v1/sales/cuts/monthly-stats
     */
    public function monthlyStats(): JsonResponse
    {
        try {
            $stats = $this->salesCutService->getMonthlyStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al obtener estadísticas mensuales', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas mensuales',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar notas del corte
     * PATCH /api/v1/sales/cuts/{id}/notes
     */
    public function updateNotes(int $id, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'notes' => 'required|string|max:1000',
            ]);

            $cut = SalesCut::findOrFail($id);
            $cut->update(['notes' => $validated['notes']]);

            return response()->json([
                'success' => true,
                'message' => 'Notas actualizadas exitosamente',
                'data' => $cut,
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al actualizar notas', [
                'cut_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar notas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exportar corte a Excel
     * GET /api/v1/sales/cuts/{id}/export
     */
    public function export(int $id): BinaryFileResponse|JsonResponse
    {
        try {
            $cut = SalesCut::with(['items.contract.client', 'items.contract.advisor', 'closedBy'])
                ->findOrFail($id);

            $export = new SalesCutExport($cut);
            $filePath = $export->export();

            $fileName = 'corte_' . $cut->cut_date->format('Y-m-d') . '.xlsx';

            return response()->download($filePath, $fileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al exportar corte', [
                'cut_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al exportar corte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calcular corte para un período sin guardarlo (preview)
     * POST /api/v1/sales/cuts/calculate
     * 
     * Body: {
     *   "start_date": "2024-01-01",
     *   "end_date": "2024-01-31",
     *   "include_details": true
     * }
     */
    public function calculate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'include_details' => 'boolean',
            ]);

            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
            $includeDetails = $validated['include_details'] ?? true;

            // Calcular sin guardar
            $calculatedData = $this->calculatorService->calculateCut($startDate, $endDate, $includeDetails);
            
            // Determinar tipo de corte
            $cutType = $this->calculatorService->determineCutType($startDate, $endDate);

            return response()->json([
                'success' => true,
                'message' => 'Corte calculado exitosamente (no guardado)',
                'data' => array_merge($calculatedData, [
                    'cut_type' => $cutType,
                    'is_preview' => true,
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al calcular corte', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al calcular corte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear y guardar un corte calculado
     * POST /api/v1/sales/cuts
     * 
     * Body: {
     *   "start_date": "2024-01-01",
     *   "end_date": "2024-01-31",
     *   "notes": "Corte de enero 2024"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'notes' => 'nullable|string|max:1000',
            ]);

            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);

            // Calcular datos
            $calculatedData = $this->calculatorService->calculateCut($startDate, $endDate, true);
            $cutType = $this->calculatorService->determineCutType($startDate, $endDate);

            // Verificar si ya existe un corte para esta fecha
            $existingCut = SalesCut::where('cut_date', $startDate->format('Y-m-d'))->first();
            
            if ($existingCut && $cutType === 'daily') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un corte para esta fecha',
                    'data' => $existingCut,
                ], 409);
            }

            // Crear el corte
            $cut = SalesCut::create([
                'cut_date' => $startDate->format('Y-m-d'),
                'cut_type' => $cutType,
                'status' => 'open',
                'total_sales_count' => $calculatedData['total_sales_count'],
                'total_revenue' => $calculatedData['total_revenue'],
                'total_down_payments' => $calculatedData['total_down_payments'],
                'total_payments_count' => $calculatedData['total_payments_count'],
                'total_payments_received' => $calculatedData['total_payments_received'],
                'paid_installments_count' => $calculatedData['paid_installments_count'],
                'total_commissions' => $calculatedData['total_commissions'],
                'cash_balance' => $calculatedData['cash_balance'],
                'bank_balance' => $calculatedData['bank_balance'],
                'summary_data' => json_encode($calculatedData['summary_data']),
                'notes' => $validated['notes'] ?? null,
            ]);

            // Crear items del corte (contratos)
            $contracts = \Illuminate\Support\Facades\DB::table('contracts as c')
                ->whereBetween('c.sign_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->where('c.status', 'vigente')
                ->select('c.contract_id', 'c.total_price', 'c.advisor_id')
                ->get();

            foreach ($contracts as $contract) {
                // Obtener comisión real de la tabla commissions
                $realCommission = \Illuminate\Support\Facades\DB::table('commissions')
                    ->where('contract_id', $contract->contract_id)
                    ->where('employee_id', $contract->advisor_id)
                    ->whereNull('parent_commission_id') // Solo comisión padre
                    ->where('status', '!=', 'cancelled')
                    ->sum('commission_amount') ?? 0;

                $cut->items()->create([
                    'item_type' => 'sale',
                    'contract_id' => $contract->contract_id,
                    'employee_id' => $contract->advisor_id,
                    'amount' => $contract->total_price,
                    'commission' => round($realCommission, 2),
                ]);
            }

            // Crear items de pagos
            $payments = \Illuminate\Support\Facades\DB::table('payments as p')
                ->join('payment_schedules as ps', 'p.schedule_id', '=', 'ps.schedule_id')
                ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
                ->whereBetween('p.payment_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->where('c.status', 'vigente')
                ->select('ps.contract_id', 'p.schedule_id', 'p.amount', 'p.method')
                ->get();

            foreach ($payments as $payment) {
                $cut->items()->create([
                    'item_type' => 'payment',
                    'contract_id' => $payment->contract_id,
                    'payment_schedule_id' => $payment->schedule_id,
                    'amount' => $payment->amount,
                    'payment_method' => $this->mapPaymentMethod($payment->method),
                ]);
            }

            Log::info('[SalesCut] Corte creado manualmente', [
                'cut_id' => $cut->cut_id,
                'type' => $cutType,
                'period' => $calculatedData['period'],
                'items_created' => $cut->items()->count(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Corte creado y guardado exitosamente',
                'data' => $cut->load('items'),
            ], 201);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al crear corte', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear corte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Recalcular un corte existente
     * PUT /api/v1/sales/cuts/{id}/recalculate
     */
    public function recalculate(int $id): JsonResponse
    {
        try {
            $cut = SalesCut::findOrFail($id);

            if ($cut->status === 'closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede recalcular un corte cerrado',
                ], 400);
            }

            $startDate = Carbon::parse($cut->cut_date);
            $endDate = $startDate->copy(); // Por defecto, mismo día

            // Si el corte es semanal, mensual, etc., ajustar el rango
            if ($cut->cut_type === 'weekly') {
                $endDate = $startDate->copy()->addDays(6);
            } elseif ($cut->cut_type === 'monthly') {
                $endDate = $startDate->copy()->endOfMonth();
            }

            // Recalcular
            $calculatedData = $this->calculatorService->calculateCut($startDate, $endDate, true);

            // Actualizar el corte
            $cut->update([
                'total_sales_count' => $calculatedData['total_sales_count'],
                'total_revenue' => $calculatedData['total_revenue'],
                'total_down_payments' => $calculatedData['total_down_payments'],
                'total_payments_count' => $calculatedData['total_payments_count'],
                'total_payments_received' => $calculatedData['total_payments_received'],
                'paid_installments_count' => $calculatedData['paid_installments_count'],
                'total_commissions' => $calculatedData['total_commissions'],
                'cash_balance' => $calculatedData['cash_balance'],
                'bank_balance' => $calculatedData['bank_balance'],
                'summary_data' => json_encode($calculatedData['summary_data']),
            ]);

            Log::info('[SalesCut] Corte recalculado', [
                'cut_id' => $cut->cut_id,
                'date' => $cut->cut_date,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Corte recalculado exitosamente',
                'data' => $cut->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('[SalesCut] Error al recalcular corte', [
                'cut_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al recalcular corte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mapear método de pago de payments a sales_cut_items
     */
    private function mapPaymentMethod(string $method): string
    {
        $map = [
            'efectivo' => 'cash',
            'transferencia' => 'bank_transfer',
            'tarjeta' => 'credit_card',
            'yape' => 'bank_transfer',
            'plin' => 'bank_transfer',
            'otro' => 'bank_transfer',
        ];

        return $map[$method] ?? 'cash';
    }
}
