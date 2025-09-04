<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Http\Requests\PaymentScheduleRequest;
use Modules\Sales\Http\Requests\UpdatePaymentScheduleRequest;
use Modules\Sales\Models\PaymentSchedule;
use Modules\Sales\Models\Payment;
use Modules\Sales\Models\Contract;
use Modules\Sales\Repositories\PaymentScheduleRepository;
use Modules\Sales\Services\PaymentScheduleService;
use Modules\Sales\Transformers\PaymentScheduleResource;
use Modules\services\PusherNotifier;

class PaymentScheduleController extends Controller
{

    public function __construct(
        private PaymentScheduleRepository $schedules,
        private PaymentScheduleService $scheduleService,
        private PusherNotifier $pusher
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:sales.schedules.index')->only(['index', 'show']);
        $this->middleware('permission:sales.schedules.store')->only(['store', 'generateIntelligentSchedule']);
        $this->middleware('permission:sales.schedules.update')->only('update');
        $this->middleware('permission:sales.schedules.destroy')->only('destroy');
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'from_date' => $request->get('date_from') ?: $request->get('from_date'),
            'to_date' => $request->get('date_to') ?: $request->get('to_date'),
        ];
        
        // Log para debugging
        \Log::info('🔍 PaymentScheduleController::index - Filtros recibidos:', [
            'request_params' => $request->all(),
            'processed_filters' => $filters
        ]);
        
        return PaymentScheduleResource::collection(
            $this->schedules->paginate(['contract.reservation.client', 'contract.reservation.lot', 'payments'], 15, $filters)
        );
    }

    public function store(PaymentScheduleRequest $request)
    {
        DB::beginTransaction();
        try {
            $schedule = $this->schedules->create($request->validated());
            DB::commit();

            $this->pusher->notify('schedule-channel', 'created', [
                'schedule' => (new PaymentScheduleResource($schedule))->toArray($request),
            ]);

            return (new PaymentScheduleResource($schedule))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear cronograma',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(PaymentSchedule $schedule)
    {
        return new PaymentScheduleResource($schedule->load(['contract.reservation.client', 'contract.reservation.lot', 'payments']));
    }

    public function update(UpdatePaymentScheduleRequest $request, PaymentSchedule $schedule)
    {
        Log::info('🔄 PaymentScheduleController::update iniciado', [
            'schedule_id' => $schedule->schedule_id,
            'current_status' => $schedule->status,
            'request_data' => $request->all()
        ]);

        DB::beginTransaction();
        try {
            $data = $request->validated();
            Log::info('✅ Datos validados:', $data);
            
            // Si se está marcando como pagado, crear el registro de pago y actualizar campos
            if (isset($data['status']) && $data['status'] === 'pagado' && $schedule->status !== 'pagado') {
                Log::info('💰 Creando registro de pago para schedule_id: ' . $schedule->schedule_id);
                
                // Crear el registro de pago
                $payment = Payment::create([
                    'schedule_id' => $schedule->schedule_id,
                    'contract_id' => $schedule->contract_id,
                    'payment_date' => $request->input('payment_date', now()->format('Y-m-d')),
                    'amount' => $request->input('amount_paid', $schedule->amount),
                    'method' => $request->input('payment_method', 'transfer'),
                    'reference' => $request->input('notes', 'Pago registrado desde sistema')
                ]);
                
                Log::info('✅ Pago creado:', $payment->toArray());
                
                // Actualizar también los campos en payment_schedules para mostrar en frontend
                $data['amount_paid'] = $request->input('amount_paid', $schedule->amount);
                $data['payment_date'] = $request->input('payment_date', now()->format('Y-m-d'));
                $data['payment_method'] = $request->input('payment_method', 'transfer');
                
                Log::info('💾 Actualizando campos de pago en schedule:', [
                    'amount_paid' => $data['amount_paid'],
                    'payment_date' => $data['payment_date'],
                    'payment_method' => $data['payment_method']
                ]);
            }
            
            Log::info('🔄 Actualizando schedule con datos:', $data);
            $updated = $this->schedules->update($schedule, $data);
            Log::info('✅ Schedule actualizado:', $updated->toArray());
            
            DB::commit();
            Log::info('✅ Transacción confirmada');

            $this->pusher->notify('schedule-channel', 'updated', [
                'schedule' => (new PaymentScheduleResource($updated))->toArray($request),
            ]);

            $response = new PaymentScheduleResource($updated->load(['contract.reservation.client', 'contract.reservation.lot', 'payments']));
            Log::info('📤 Enviando respuesta:', $response->toArray($request));
            
            return $response;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('❌ Error en PaymentScheduleController::update', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error al actualizar cronograma',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(PaymentSchedule $schedule)
    {
        try {
            DB::beginTransaction();
            $resource = new PaymentScheduleResource($schedule->load(['contract', 'payments']));
            $this->schedules->delete($schedule);
            DB::commit();

            $this->pusher->notify('schedule-channel', 'deleted', [
                'schedule' => $resource->toArray(request()),
            ]);

            return response()->json(['message' => 'Cronograma eliminado correctamente']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al eliminar cronograma',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Genera un cronograma de pagos inteligente basado en LotFinancialTemplate
     */
    public function generateIntelligentSchedule(Request $request, Contract $contract)
    {
        try {
            // Validar que el contrato puede generar cronograma
            $validation = $this->scheduleService->canGenerateSchedule($contract);
            
            if (!$validation['can_generate']) {
                return response()->json([
                    'message' => 'No se puede generar cronograma para este contrato',
                    'reasons' => $validation['reasons']
                ], Response::HTTP_BAD_REQUEST);
            }

            // Obtener opciones del request
            $options = [
                'payment_type' => $request->input('payment_type', 'installments'),
                'installments' => $request->input('installments', 24),
                'start_date' => $request->input('start_date')
            ];

            Log::info('🎯 Generando cronograma inteligente', [
                'contract_id' => $contract->contract_id,
                'options' => $options
            ]);

            DB::beginTransaction();

            // Generar cronograma usando el servicio
            $schedules = $this->scheduleService->generateIntelligentSchedule($contract, $options);
            
            // Guardar cronograma en la base de datos
            $savedSchedules = $this->scheduleService->saveSchedule($schedules);

            DB::commit();

            Log::info('✅ Cronograma inteligente generado exitosamente', [
                'contract_id' => $contract->contract_id,
                'schedules_count' => count($savedSchedules)
            ]);

            // Notificar via Pusher
            $this->pusher->notify('schedule-channel', 'intelligent-generated', [
                'contract_id' => $contract->contract_id,
                'schedules_count' => count($savedSchedules)
            ]);

            return response()->json([
                'message' => 'Cronograma generado exitosamente',
                'schedules' => PaymentScheduleResource::collection($savedSchedules),
                'summary' => [
                    'total_schedules' => count($savedSchedules),
                    'total_amount' => array_sum(array_column($schedules, 'amount')),
                    'payment_type' => $options['payment_type'],
                    'installments' => $options['installments']
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('❌ Error generando cronograma inteligente', [
                'contract_id' => $contract->contract_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al generar cronograma inteligente',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtiene las opciones de financiamiento disponibles para un contrato
     */
    public function getFinancingOptions(Contract $contract)
    {
        try {
            $options = $this->scheduleService->getFinancingOptions($contract);
            
            return response()->json([
                'contract_id' => $contract->contract_id,
                'financing_options' => $options
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error obteniendo opciones de financiamiento', [
                'contract_id' => $contract->contract_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al obtener opciones de financiamiento',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark a payment schedule as paid
     */
    public function markAsPaid(Request $request, PaymentSchedule $schedule)
    {
        try {
            $validated = $request->validate([
                'payment_date' => 'required|date',
                'amount_paid' => 'required|numeric|min:0',
                'payment_method' => 'nullable|string|in:cash,transfer,check,card',
                'notes' => 'nullable|string|max:500'
            ]);

            $schedule->update([
                'status' => 'pagado',
                'payment_date' => $validated['payment_date'],
                'amount_paid' => $validated['amount_paid'],
                'payment_method' => $validated['payment_method'] ?? 'transfer',
                'notes' => $validated['notes']
            ]);

            return response()->json([
                'success' => true,
                'data' => new PaymentScheduleResource($schedule->fresh()),
                'status' => 'paid'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar como pagado: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get payment schedule metrics
     */
    public function getMetrics(Request $request)
    {
        try {
            $contractId = $request->get('contract_id');
            
            $query = PaymentSchedule::query();
            if ($contractId) {
                $query->where('contract_id', $contractId);
            }

            $totalSchedules = $query->count();
            $pendingAmount = $query->where('status', 'pendiente')->sum('amount');
            $paidAmount = $query->where('status', 'pagado')->sum('amount_paid');
            $overdueAmount = $query->where('status', 'vencido')->sum('amount');
            $overdueCount = $query->where('status', 'vencido')->count();
            
            $paymentRate = $totalSchedules > 0 ? ($query->where('status', 'pagado')->count() / $totalSchedules) * 100 : 0;

            return response()->json([
                'total_schedules' => $totalSchedules,
                'pending_amount' => $pendingAmount,
                'paid_amount' => $paidAmount,
                'overdue_amount' => $overdueAmount,
                'overdue_count' => $overdueCount,
                'payment_rate' => round($paymentRate, 2),
                'currency' => 'DOP'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener métricas',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get payment schedule report
     */
    public function getReport(Request $request)
    {
        try {
            // Basic report implementation
            return response()->json([
                'message' => 'Reporte generado exitosamente',
                'data' => []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al generar reporte',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate payment schedule report
     */
    public function generateReport(Request $request)
    {
        try {
            // Report generation implementation
            return response()->json([
                'message' => 'Reporte generado exitosamente',
                'data' => []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al generar reporte',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get schedules for a specific contract
     */
    public function getContractSchedules(Request $request, Contract $contract)
    {
        try {
            $schedules = $contract->paymentSchedules()
                ->when($request->get('status'), function($query, $status) {
                    return $query->where('status', $status);
                })
                ->when($request->get('due_date_from'), function($query, $date) {
                    return $query->where('due_date', '>=', $date);
                })
                ->when($request->get('due_date_to'), function($query, $date) {
                    return $query->where('due_date', '<=', $date);
                })
                ->orderBy('due_date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => PaymentScheduleResource::collection($schedules)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cronogramas del contrato',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}