<?php

namespace Modules\Collections\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Collections\Models\CustomerPayment;
use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Services\PaymentDetectionService;
use Modules\CRM\Models\Client;
use Exception;

class CustomerPaymentController extends Controller
{
    protected PaymentDetectionService $paymentDetectionService;

    public function __construct(PaymentDetectionService $paymentDetectionService)
    {
        $this->paymentDetectionService = $paymentDetectionService;
    }

    /**
     * Obtiene lista de pagos con filtros
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = CustomerPayment::with(['client', 'accountReceivable', 'processor']);

            // Filtros
            if ($request->has('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            if ($request->has('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }

            if ($request->has('date_from') && $request->has('date_to')) {
                $query->byDateRange($request->date_from, $request->date_to);
            }

            if ($request->has('affects_commissions')) {
                $query->where('affects_commissions', $request->boolean('affects_commissions'));
            }

            // Paginación
            $perPage = $request->get('per_page', 15);
            $payments = $query->orderBy('payment_date', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $payments,
                'message' => 'Pagos obtenidos exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error obteniendo pagos', [
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los pagos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene un pago específico
     */
    public function show(int $paymentId): JsonResponse
    {
        try {
            $payment = CustomerPayment::with([
                'client',
                'accountReceivable.contract',
                'processor',
                'commissionVerifications.commission'
            ])->findOrFail($paymentId);

            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Pago obtenido exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error obteniendo pago', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Pago no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Crea un nuevo pago de cliente
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:clients,client_id',
            'ar_id' => 'required|exists:accounts_receivable,ar_id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:3',
            'payment_method' => 'required|in:efectivo,transferencia,cheque,tarjeta_credito,tarjeta_debito',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Crear el pago
            $payment = CustomerPayment::create([
                'client_id' => $request->client_id,
                'ar_id' => $request->ar_id,
                'payment_number' => CustomerPayment::generatePaymentNumber(),
                'payment_date' => $request->payment_date,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'payment_method' => $request->payment_method,
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
                'processed_by' => auth()->id()
            ]);

            // Procesar para comisiones usando el servicio
            $this->paymentDetectionService->processPaymentForCommissions($payment);

            DB::commit();

            // Recargar con relaciones
            $payment->load(['client', 'accountReceivable', 'processor']);

            Log::info('Pago creado exitosamente', [
                'payment_id' => $payment->payment_id,
                'client_id' => $payment->client_id,
                'amount' => $payment->amount,
                'affects_commissions' => $payment->affects_commissions
            ]);

            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Pago creado exitosamente'
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error creando pago', [
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza un pago existente
     */
    public function update(Request $request, int $paymentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_date' => 'sometimes|date',
            'amount' => 'sometimes|numeric|min:0.01',
            'currency' => 'sometimes|string|max:3',
            'payment_method' => 'sometimes|in:efectivo,transferencia,cheque,tarjeta_credito,tarjeta_debito',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $payment = CustomerPayment::findOrFail($paymentId);
            $originalAmount = $payment->amount;
            $originalDate = $payment->payment_date;

            // Actualizar campos permitidos
            $payment->update($request->only([
                'payment_date', 'amount', 'currency', 'payment_method',
                'reference_number', 'notes'
            ]));

            // Si cambió el monto o fecha, reprocesar para comisiones
            if ($originalAmount != $payment->amount || $originalDate != $payment->payment_date) {
                $this->paymentDetectionService->processPaymentForCommissions($payment);
            }

            DB::commit();

            $payment->load(['client', 'accountReceivable', 'processor']);

            Log::info('Pago actualizado exitosamente', [
                'payment_id' => $payment->payment_id,
                'changes' => $request->only([
                    'payment_date', 'amount', 'currency', 'payment_method',
                    'reference_number', 'notes'
                ])
            ]);

            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Pago actualizado exitosamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error actualizando pago', [
                'payment_id' => $paymentId,
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina un pago (soft delete)
     */
    public function destroy(int $paymentId): JsonResponse
    {
        try {
            $payment = CustomerPayment::findOrFail($paymentId);

            // Verificar si tiene verificaciones de comisión
            if ($payment->hasCommissionVerifications()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar un pago que tiene verificaciones de comisión asociadas'
                ], 422);
            }

            $payment->delete();

            Log::info('Pago eliminado exitosamente', [
                'payment_id' => $paymentId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pago eliminado exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error eliminando pago', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Redetecta el tipo de cuota para un pago específico
     */
    public function redetectInstallment(int $paymentId): JsonResponse
    {
        try {
            $payment = CustomerPayment::findOrFail($paymentId);

            $newInstallmentType = $this->paymentDetectionService->redetectInstallmentType(
                $payment,
                auth()->id()
            );

            $payment->refresh();
            $payment->load(['client', 'accountReceivable']);

            Log::info('Tipo de cuota redetectado', [
                'payment_id' => $paymentId,
                'new_installment_type' => $newInstallmentType,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => $payment,
                    'new_installment_type' => $newInstallmentType
                ],
                'message' => 'Tipo de cuota redetectado exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error redetectando tipo de cuota', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al redetectar el tipo de cuota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene estadísticas de detección de pagos
     */
    public function getDetectionStats(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'date_from', 'date_to', 'client_id', 'installment_type'
            ]);

            $stats = $this->paymentDetectionService->getDetectionStats($filters);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error obteniendo estadísticas de detección', [
                'filters' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene pagos que afectan comisiones
     */
    public function getCommissionAffectingPayments(Request $request): JsonResponse
    {
        try {
            $query = CustomerPayment::with(['client', 'accountReceivable.contract'])
                ->where('affects_commissions', true);

            if ($request->has('installment_type')) {
                $query->where('installment_type', $request->installment_type);
            }

            if ($request->has('date_from') && $request->has('date_to')) {
                $query->byDateRange($request->date_from, $request->date_to);
            }

            $perPage = $request->get('per_page', 15);
            $payments = $query->orderBy('payment_date', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $payments,
                'message' => 'Pagos que afectan comisiones obtenidos exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error obteniendo pagos que afectan comisiones', [
                'filters' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los pagos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}