<?php

namespace Modules\HumanResources\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CommissionPaymentVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\HumanResources\app\Services\SplitPaymentService;
use Exception;
use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Models\CustomerPayment;
use Modules\HumanResources\app\Services\CommissionPaymentVerificationService;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Repositories\CommissionRepository;
use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Services\CommissionVerificationService;
use Modules\HumanResources\Transformers\CommissionResource;

class CommissionController extends Controller
{
    public function __construct(
        protected CommissionRepository $commissionRepo,
        protected CommissionService $commissionService,
        protected CommissionPaymentVerificationService $paymentVerificationService,
        protected CommissionVerificationService $verificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'employee_id', 
            'payment_status', 
            'period_month', 
            'period_year',
            'commission_period',
            'payment_period',
            'status'
        ]);

        if ($request->has('paginate') && $request->paginate === 'true') {
            $commissions = $this->commissionRepo->getPaginated($filters, $request->get('per_page', 15));
            return response()->json([
                'success' => true,
                'data' => CommissionResource::collection($commissions->items()),
                'meta' => [
                    'current_page' => $commissions->currentPage(),
                    'last_page' => $commissions->lastPage(),
                    'per_page' => $commissions->perPage(),
                    'total' => $commissions->total()
                ],
                'message' => 'Comisiones obtenidas exitosamente'
            ]);
        } else {
            $commissions = $this->commissionRepo->getAll($filters);
            return response()->json([
                'success' => true,
                'data' => CommissionResource::collection($commissions),
                'message' => 'Comisiones obtenidas exitosamente'
            ]);
        }
    }


    public function show(string $id): JsonResponse
    {
        $commission = $this->commissionRepo->findById((int) $id);

        if (!$commission) {
            return response()->json([
                'success' => false,
                'message' => 'Comisión no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new CommissionResource($commission),
            'message' => 'Comisión obtenida exitosamente'
        ]);
    }

    public function processForPeriod(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030'
        ]);

        try {
            $commissions = $this->commissionService->processCommissionsForPeriod(
                $request->month,
                $request->year
            );

            return response()->json([
                'success' => true,
                'data' => CommissionResource::collection($commissions),
                'message' => 'Comisiones procesadas exitosamente',
                'count' => count($commissions)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar comisiones: ' . $e->getMessage()
            ], 500);
        }
    }

    public function pay(Request $request): JsonResponse
    {
        $request->validate([
            'commission_ids' => 'required|array',
            'commission_ids.*' => 'integer|exists:commissions,commission_id'
        ]);

        try {
            $success = $this->commissionService->payCommissions($request->commission_ids);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Comisiones pagadas exitosamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudieron pagar las comisiones'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al pagar comisiones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Paga una parte específica de una comisión dividida
     * Valida que el cliente haya pagado la cuota correspondiente
     */
    public function payPart(Request $request, int $commissionId): JsonResponse
    {
        $request->validate([
            'payment_part' => 'required|integer|in:1,2'
        ]);

        try {
            Log::info('=== INICIO PAGO PARTE COMISIÓN DESDE FRONTEND ===', [
                'commission_id' => $commissionId,
                'payment_part' => $request->payment_part,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'user_id' => auth()->id()
            ]);

            // Buscar la comisión específica
            $commission = $this->commissionRepo->findById($commissionId);
            
            if (!$commission) {
                Log::error('Comisión no encontrada', ['commission_id' => $commissionId]);
                return response()->json([
                    'success' => false,
                    'message' => 'Comisión no encontrada'
                ], 404);
            }

            Log::info('Comisión encontrada - Datos iniciales', [
                'commission_id' => $commission->commission_id,
                'contract_id' => $commission->contract_id,
                'payment_part' => $commission->payment_part,
                'parent_commission_id' => $commission->parent_commission_id,
                'status' => $commission->status,
                'payment_verification_status' => $commission->payment_verification_status,
                'requires_client_payment_verification' => $commission->requires_client_payment_verification,
                'is_eligible_for_payment' => $commission->is_eligible_for_payment,
                'first_payment_verified_at' => $commission->first_payment_verified_at,
                'second_payment_verified_at' => $commission->second_payment_verified_at
            ]);

            // Verificar si es una comisión padre (parent_commission_id = null)
            if (is_null($commission->parent_commission_id)) {
                // Es una comisión padre - verificar si necesitamos buscar una comisión hija
                
                // Si el payment_part solicitado no coincide con el de la comisión padre,
                // buscar la comisión hija correspondiente
                if ($commission->payment_part !== $request->payment_part) {
                    Log::info('Comisión padre detectada, buscando comisión hija', [
                        'parent_commission_id' => $commission->commission_id,
                        'parent_payment_part' => $commission->payment_part,
                        'requested_payment_part' => $request->payment_part
                    ]);

                    $childCommission = Commission::where('parent_commission_id', $commission->commission_id)
                        ->where('payment_part', $request->payment_part)
                        ->first();

                    if (!$childCommission) {
                        Log::error('Comisión hija no encontrada', [
                            'parent_commission_id' => $commission->commission_id,
                            'parent_payment_part' => $commission->payment_part,
                            'requested_payment_part' => $request->payment_part
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => 'No se encontró la parte de comisión solicitada'
                        ], 404);
                    }

                    // Usar la comisión hija para el resto del proceso
                    $commission = $childCommission;
                    
                    Log::info('Comisión hija encontrada', [
                        'child_commission_id' => $commission->commission_id,
                        'payment_part' => $commission->payment_part,
                        'parent_commission_id' => $commission->parent_commission_id
                    ]);
                } else {
                    // El payment_part coincide, usar la comisión padre directamente
                    Log::info('Usando comisión padre directamente', [
                        'commission_id' => $commission->commission_id,
                        'payment_part' => $commission->payment_part
                    ]);
                }
            } else {
                // Es una comisión hija - verificar que el payment_part coincida
                if ($commission->payment_part !== $request->payment_part) {
                    Log::error('Payment part no coincide - comisión hija incorrecta', [
                        'commission_payment_part' => $commission->payment_part,
                        'requested_payment_part' => $request->payment_part,
                        'commission_id' => $commission->commission_id,
                        'parent_commission_id' => $commission->parent_commission_id
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'La parte de pago no coincide con la comisión solicitada'
                    ], 400);
                }
                
                Log::info('Usando comisión hija directamente', [
                    'commission_id' => $commission->commission_id,
                    'payment_part' => $commission->payment_part,
                    'parent_commission_id' => $commission->parent_commission_id
                ]);
            }

            // Verificar que la comisión no esté ya pagada
            if ($commission->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta parte de la comisión ya ha sido pagada'
                ], 400);
            }

            Log::info('Iniciando verificación de pagos del cliente', [
                'commission_id' => $commission->commission_id,
                'contract_id' => $commission->contract_id,
                'requires_verification' => $commission->requires_client_payment_verification
            ]);

            // Verificar que el cliente haya pagado la cuota correspondiente
            $verificationResult = $this->paymentVerificationService->verifyClientPayments($commission);
            
            Log::info('Resultado de verificación de pagos', [
                'commission_id' => $commission->commission_id,
                'verification_result' => $verificationResult,
                'first_payment' => $verificationResult['first_payment'] ?? 'N/A',
                'second_payment' => $verificationResult['second_payment'] ?? 'N/A'
            ]);
            
            $canPay = false;
            
            // Si la comisión no requiere verificación de pagos del cliente, permitir el pago
            if (!$commission->requires_client_payment_verification) {
                $canPay = $commission->is_eligible_for_payment;
                Log::info('Comisión no requiere verificación - usando is_eligible_for_payment', [
                    'commission_id' => $commission->commission_id,
                    'is_eligible_for_payment' => $commission->is_eligible_for_payment,
                    'can_pay' => $canPay
                ]);
            } else {
                // Para comisiones que requieren verificación, verificar los pagos específicos
                if ($request->payment_part === 1) {
                    $canPay = $verificationResult['first_payment'] ?? false;
                    Log::info('Verificando payment_part = 1', [
                        'commission_id' => $commission->commission_id,
                        'first_payment_verified' => $verificationResult['first_payment'] ?? false,
                        'can_pay' => $canPay
                    ]);
                } elseif ($request->payment_part === 2) {
                    $canPay = $verificationResult['second_payment'] ?? false;
                    Log::info('Verificando payment_part = 2', [
                        'commission_id' => $commission->commission_id,
                        'second_payment_verified' => $verificationResult['second_payment'] ?? false,
                        'can_pay' => $canPay
                    ]);
                }
            }

            if (!$canPay) {
                Log::error('Pago rechazado - Cliente no ha pagado cuota correspondiente', [
                    'commission_id' => $commission->commission_id,
                    'payment_part' => $request->payment_part,
                    'can_pay' => $canPay,
                    'requires_verification' => $commission->requires_client_payment_verification,
                    'verification_result' => $verificationResult
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede pagar esta parte de la comisión porque el cliente no ha pagado la cuota correspondiente',
                    'verification_details' => $verificationResult
                ], 400);
            }

            Log::info('Procesando pago de comisión', [
                'commission_id' => $commission->commission_id,
                'payment_part' => $request->payment_part,
                'can_pay' => $canPay
            ]);

            // Procesar el pago
            $result = $this->commissionService->processCommissionPayment($commission, $request->payment_part);

            Log::info('Resultado del procesamiento de pago', [
                'commission_id' => $commission->commission_id,
                'payment_part' => $request->payment_part,
                'result_success' => $result['success'],
                'result_message' => $result['message'] ?? 'N/A'
            ]);

            if ($result['success']) {
                Log::info('=== PAGO PROCESADO EXITOSAMENTE ===', [
                    'commission_id' => $commission->commission_id,
                    'payment_part' => $request->payment_part,
                    'timestamp' => now()->format('Y-m-d H:i:s')
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Pago procesado exitosamente',
                    'data' => $result['data']
                ]);
            } else {
                Log::error('=== ERROR EN PROCESAMIENTO DE PAGO ===', [
                    'commission_id' => $commission->commission_id,
                    'payment_part' => $request->payment_part,
                    'error_message' => $result['message'],
                    'timestamp' => now()->format('Y-m-d H:i:s')
                ]);
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error al pagar parte de comisión', [
                'commission_id' => $commissionId,
                'payment_part' => $request->payment_part,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el detalle de ventas individuales con sus comisiones para un asesor
     */
    public function getSalesDetail(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|integer|exists:employees,employee_id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030'
        ]);

        try {
            $salesDetail = $this->commissionService->getAdvisorSalesDetail(
                $request->employee_id,
                $request->month,
                $request->year
            );

            return response()->json($salesDetail);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalle de ventas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crea un pago dividido para una comisión
     */
    public function createSplitPayment(Request $request, int $commissionId): JsonResponse
    {
        $request->validate([
            'percentage' => 'required|numeric|min:0.01|max:100',
            'payment_period' => 'required|string|regex:/^\d{4}-\d{2}$/'
        ]);

        try {
            $result = $this->commissionService->createSplitPayment($commissionId, [
                'percentage' => $request->percentage,
                'payment_period' => $request->payment_period
            ]);

            if ($result['success']) {
                return response()->json($result);
            } else {
                return response()->json($result, 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear pago dividido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene comisiones por período de generación
     */
    public function getByCommissionPeriod(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|string|regex:/^\d{4}-\d{2}$/'
        ]);

        try {
            $result = $this->commissionService->getCommissionsByPeriod($request->period);
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => CommissionResource::collection($result['commissions']),
                    'meta' => [
                        'total_amount' => $result['total_amount'],
                        'count' => $result['count'],
                        'period' => $request->period
                    ],
                    'message' => 'Comisiones obtenidas exitosamente'
                ]);
            } else {
                return response()->json($result, 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener comisiones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene comisiones pendientes para un período
     */
    public function getPendingCommissions(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|string|regex:/^\d{4}-\d{2}$/'
        ]);

        try {
            $result = $this->commissionService->getPendingCommissions($request->period);
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => CommissionResource::collection($result['commissions']),
                    'meta' => [
                        'total_amount' => $result['total_amount'],
                        'count' => $result['count'],
                        'period' => $request->period
                    ],
                    'message' => 'Comisiones pendientes obtenidas exitosamente'
                ]);
            } else {
                return response()->json($result, 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener comisiones pendientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesa comisiones para incluir en nómina
     */
    public function processForPayroll(Request $request): JsonResponse
    {
        $request->validate([
            'commission_period' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'payment_period' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'commission_ids' => 'sometimes|array',
            'commission_ids.*' => 'integer|exists:commissions,commission_id'
        ]);

        try {
            $result = $this->commissionService->processCommissionsForPayroll(
                $request->commission_period,
                $request->payment_period,
                $request->get('commission_ids', [])
            );

            if ($result['success']) {
                return response()->json($result);
            } else {
                return response()->json($result, 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar comisiones para nómina: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el resumen de pagos divididos para una comisión
     */
    public function getSplitPaymentSummary(int $commissionId): JsonResponse
    {
        try {
            $result = $this->commissionService->getSplitPaymentSummary($commissionId);
            
            if ($result['success']) {
                return response()->json($result);
            } else {
                return response()->json($result, 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen de pagos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marca múltiples comisiones como pagadas
     */
    public function markMultipleAsPaid(Request $request): JsonResponse
    {
        $request->validate([
            'commission_ids' => 'required|array',
            'commission_ids.*' => 'integer|exists:commissions,commission_id'
        ]);

        try {
            $result = $this->commissionService->markMultipleAsPaid($request->commission_ids);
            
            if ($result['success']) {
                return response()->json($result);
            } else {
                return response()->json($result, 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar comisiones como pagadas: ' . $e->getMessage()
            ], 500);
        }
    }

    // === MÉTODOS PARA COMISIONES CONDICIONADAS ===

    /**
     * Obtiene comisiones que requieren verificación de pagos
     */
    public function getCommissionsRequiringVerification(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'employee_id', 'payment_dependency_type', 'date_from', 'date_to'
            ]);

            $query = $this->commissionRepo->getCommissionsRequiringVerification($filters);

            if ($request->has('paginate') && $request->paginate === 'true') {
                $commissions = $query->paginate($request->get('per_page', 15));
                return response()->json([
                    'success' => true,
                    'data' => CommissionResource::collection($commissions->items()),
                    'meta' => [
                        'current_page' => $commissions->currentPage(),
                        'last_page' => $commissions->lastPage(),
                        'per_page' => $commissions->perPage(),
                        'total' => $commissions->total()
                    ],
                    'message' => 'Comisiones que requieren verificación obtenidas exitosamente'
                ]);
            } else {
                $commissions = $query->get();
                return response()->json([
                    'success' => true,
                    'data' => CommissionResource::collection($commissions),
                    'message' => 'Comisiones que requieren verificación obtenidas exitosamente'
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error obteniendo comisiones que requieren verificación', [
                'filters' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las comisiones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica manualmente los pagos de una comisión
     */
    public function verifyCommissionPayments(int $commissionId): JsonResponse
    {
        try {
            $commission = $this->commissionRepo->findById($commissionId);
            
            if (!$commission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comisión no encontrada'
                ], 404);
            }

            $results = $this->paymentVerificationService->verifyClientPayments($commission);

            Log::info('Verificación manual de comisión completada', [
                'commission_id' => $commissionId,
                'user_id' => auth()->id(),
                'results' => $results
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => 'Verificación de pagos completada exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error en verificación manual de comisión', [
                'commission_id' => $commissionId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar los pagos de la comisión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el estado de verificación de una comisión
     */
    public function getVerificationStatus(int $commissionId): JsonResponse
    {
        try {
            $commission = $this->commissionRepo->findById($commissionId);
            
            if (!$commission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comisión no encontrada'
                ], 404);
            }

            $verificationData = [
                'commission_id' => $commission->commission_id,
                'payment_dependency_type' => $commission->payment_dependency_type,
                'payment_verification_status' => $commission->payment_verification_status,
                'client_payments_verified' => $commission->client_payments_verified,
                'required_client_payments' => $commission->required_client_payments,
                'auto_verification_enabled' => $commission->auto_verification_enabled,
                'next_verification_date' => $commission->next_verification_date,
                'verification_notes' => $commission->verification_notes,
                'payment_verifications' => $commission->paymentVerifications()->with('customerPayment')->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $verificationData,
                'message' => 'Estado de verificación obtenido exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error obteniendo estado de verificación', [
                'commission_id' => $commissionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el estado de verificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza la configuración de verificación de una comisión
     */
    public function updateVerificationSettings(Request $request, int $commissionId): JsonResponse
    {
        $request->validate([
            'payment_dependency_type' => 'sometimes|in:none,first_payment_only,second_payment_only,both_payments,any_payment',
            'required_client_payments' => 'sometimes|integer|min:0',
            'auto_verification_enabled' => 'sometimes|boolean'
        ]);

        try {
            $commission = $this->commissionRepo->findById($commissionId);
            
            if (!$commission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comisión no encontrada'
                ], 404);
            }

            $updateData = $request->only([
                'payment_dependency_type',
                'required_client_payments', 
                'auto_verification_enabled'
            ]);

            $commission->update($updateData);

            Log::info('Configuración de verificación actualizada', [
                'commission_id' => $commissionId,
                'user_id' => auth()->id(),
                'changes' => $updateData
            ]);

            return response()->json([
                'success' => true,
                'data' => new CommissionResource($commission->fresh()),
                'message' => 'Configuración de verificación actualizada exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error actualizando configuración de verificación', [
                'commission_id' => $commissionId,
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la configuración',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene estadísticas de verificación
     */
    public function getVerificationStats(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'date_from', 'date_to', 'payment_dependency_type'
            ]);

            $stats = $this->verificationService->getVerificationStats($filters);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Estadísticas de verificación obtenidas exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error obteniendo estadísticas de verificación', [
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
     * Procesa verificaciones automáticas pendientes
     */
    public function processAutomaticVerifications(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 50);
            
            // Obtener comisiones que necesitan verificación automática
            $commissions = $this->commissionRepo->getCommissionsRequiringVerification()->limit($limit)->get();
            
            $results = [
                'processed' => 0,
                'verified' => 0,
                'errors' => 0,
                'details' => []
            ];

            foreach ($commissions as $commission) {
                try {
                    $verificationResult = $this->paymentVerificationService->verifyClientPayments($commission);
                    
                    $results['processed']++;
                    if ($verificationResult['first_payment'] || $verificationResult['second_payment']) {
                        $results['verified']++;
                    }
                    
                    $results['details'][] = [
                        'commission_id' => $commission->commission_id,
                        'result' => $verificationResult
                    ];
                    
                } catch (Exception $e) {
                    $results['errors']++;
                    $results['details'][] = [
                        'commission_id' => $commission->commission_id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            Log::info('Procesamiento automático de verificaciones completado', [
                'user_id' => auth()->id(),
                'results' => $results
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => 'Verificaciones automáticas procesadas exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error en procesamiento automático de verificaciones', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar las verificaciones automáticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function debugPayPart($commissionId)
    {
        Log::info('DEBUG: Iniciando debugPayPart', [
            'commission_id' => $commissionId,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'method' => 'debugPayPart'
        ]);

        // Obtener información de otras comisiones
        $otherCommissions = Commission::select('commission_id', 'status', 'payment_dependency_type', 'verification_status')
            ->take(10)
            ->get();

        Log::info('DEBUG: Otras comisiones en BD', [
            'other_commissions' => $otherCommissions->toArray()
        ]);

        // Obtener comisiones con status approved
        $approvedCommissions = Commission::select('commission_id', 'status', 'payment_dependency_type', 'verification_status')
            ->where('status', 'approved')
            ->take(5)
            ->get();

        Log::info('DEBUG: Comisiones con status approved', [
            'approved_commissions' => $approvedCommissions->toArray()
        ]);

        $commission = Commission::where('commission_id', $commissionId)->first();

        if (!$commission) {
            Log::warning('DEBUG: Comisión no encontrada', ['commission_id' => $commissionId]);
            return response()->json(['error' => 'Commission not found'], 404);
        }

        Log::info('DEBUG: Comisión encontrada', [
            'commission' => $commission->toArray()
        ]);

        if ($commission->status !== 'approved') {
            Log::warning('DEBUG: Comisión no está aprobada', [
                'commission_id' => $commissionId,
                'current_status' => $commission->status
            ]);
            return response()->json(['error' => 'Commission is not approved'], 400);
        }

        // Continuar con el proceso normal
        return $this->payPart($commissionId);
    }

    /**
     * Endpoint de debug para cambiar el estado de una comisión a 'approved'
     */
    public function debugSetApproved($commissionId)
    {
        Log::info('DEBUG: Intentando cambiar estado de comisión a approved', [
            'commission_id' => $commissionId
        ]);
        
        try {
            $commission = Commission::find($commissionId);
            
            if (!$commission) {
                Log::error('DEBUG: Comisión no encontrada', [
                    'commission_id' => $commissionId
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Comisión no encontrada'
                ], 404);
            }
            
            // Intentar actualizar el estado
            $commission->status = 'approved';
            $commission->save();
            
            Log::info('DEBUG: Estado de comisión actualizado exitosamente', [
                'commission_id' => $commissionId,
                'new_status' => 'approved'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado a approved',
                'commission' => $commission
            ]);
            
        } catch (\Exception $e) {
            Log::error('DEBUG: Error al actualizar estado de comisión', [
                'commission_id' => $commissionId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint de debugging para rastrear el proceso completo de verificación de pagos
     * Simula el proceso sin modificar datos y muestra todas las consultas ejecutadas
     */
    public function debugCommissionPaymentVerification($commissionId)
    {
        Log::info('=== DEBUG: INICIO RASTREO VERIFICACIÓN DE PAGOS ===', [
            'commission_id' => $commissionId,
            'timestamp' => now()->toDateTimeString()
        ]);
        
        try {
            $debugInfo = [
                'commission_info' => [],
                'database_queries' => [],
                'verification_process' => [],
                'final_result' => [],
                'current_state' => [],
                'comparison' => []
            ];
            
            // 1. INFORMACIÓN DE LA COMISIÓN
            $commission = Commission::find($commissionId);
            if (!$commission) {
                return response()->json([
                    'error' => 'Comisión no encontrada',
                    'commission_id' => $commissionId
                ], 404);
            }
            
            $debugInfo['commission_info'] = [
                'commission_id' => $commission->commission_id,
                'contract_id' => $commission->contract_id,
                'payment_part' => $commission->payment_part,
                'requires_client_payment_verification' => $commission->requires_client_payment_verification,
                'is_eligible_for_payment' => $commission->is_eligible_for_payment,
                'payment_verification_status' => $commission->payment_verification_status,
                'status' => $commission->status,
                'payment_type' => $commission->payment_type ?? 'N/A'
            ];
            
            // 2. CONSULTA: Verificar si hay cronograma de pagos
            $hasPaymentSchedule = \Modules\Sales\Models\PaymentSchedule::where('contract_id', $commission->contract_id)->exists();
            $debugInfo['database_queries']['payment_schedules_check'] = [
                'query' => 'SELECT EXISTS(SELECT 1 FROM payment_schedules WHERE contract_id = ' . $commission->contract_id . ')',
                'result' => $hasPaymentSchedule,
                'table' => 'payment_schedules',
                'fields_checked' => ['contract_id']
            ];
            
            // 3. CONSULTA: Obtener cuentas por cobrar
            $accountsReceivable = AccountReceivable::where('contract_id', $commission->contract_id)
                ->orderBy('due_date', 'asc')
                ->get();
            
            $debugInfo['database_queries']['accounts_receivable'] = [
                'query' => 'SELECT * FROM accounts_receivable WHERE contract_id = ' . $commission->contract_id . ' ORDER BY due_date ASC',
                'count' => $accountsReceivable->count(),
                'table' => 'accounts_receivable',
                'fields_checked' => ['contract_id', 'due_date', 'ar_id', 'original_amount', 'paid_amount', 'status'],
                'results' => $accountsReceivable->map(function($ar) {
                    return [
                        'ar_id' => $ar->ar_id,
                        'due_date' => $ar->due_date,
                        'original_amount' => $ar->original_amount,
                        'paid_amount' => $ar->paid_amount,
                        'status' => $ar->status
                    ];
                })->toArray()
            ];
            
            // 4. PROCESO DE VERIFICACIÓN SIMULADO
            if (!$commission->requires_client_payment_verification) {
                $debugInfo['verification_process']['auto_verified'] = [
                    'reason' => 'requires_client_payment_verification = false',
                    'action' => 'Marcada automáticamente como verificada',
                    'result' => 'fully_verified'
                ];
            } else {
                // Simular verificación según el tipo de contrato
                if ($hasPaymentSchedule) {
                    $debugInfo['verification_process']['type'] = 'with_payment_schedule';
                    $this->simulateVerificationWithSchedule($commission, $debugInfo);
                } else {
                    $debugInfo['verification_process']['type'] = 'without_payment_schedule';
                    $this->simulateVerificationWithoutSchedule($commission, $accountsReceivable, $debugInfo);
                }
            }
            
            // 5. CONSULTA: Verificaciones existentes
            $existingVerifications = CommissionPaymentVerification::where('commission_id', $commission->commission_id)
                ->get();
            
            $debugInfo['database_queries']['commission_payment_verifications'] = [
                'query' => 'SELECT * FROM commission_payment_verifications WHERE commission_id = ' . $commission->commission_id,
                'count' => $existingVerifications->count(),
                'table' => 'commission_payment_verifications',
                'fields_checked' => ['commission_id', 'payment_installment', 'verification_status', 'verification_date'],
                'results' => $existingVerifications->map(function($v) {
                    return [
                        'payment_installment' => $v->payment_installment,
                        'verification_status' => $v->verification_status,
                        'verification_date' => $v->verification_date,
                        'verified_amount' => $v->verified_amount
                    ];
                })->toArray()
            ];
            
            // 6. ESTADO ACTUAL VS RESULTADO SIMULADO
            $debugInfo['current_state'] = [
                'payment_verification_status' => $commission->payment_verification_status,
                'is_eligible_for_payment' => $commission->is_eligible_for_payment,
                'first_payment_verified_at' => $commission->first_payment_verified_at,
                'second_payment_verified_at' => $commission->second_payment_verified_at
            ];
            
            // 7. COMPARACIÓN Y ANÁLISIS
            $debugInfo['comparison'] = [
                'verification_matches' => $debugInfo['current_state']['payment_verification_status'] === ($debugInfo['final_result']['status'] ?? 'pending_verification'),
                'eligibility_matches' => $debugInfo['current_state']['is_eligible_for_payment'] === ($debugInfo['final_result']['is_eligible'] ?? false),
                'discrepancies' => []
            ];
            
            if (!$debugInfo['comparison']['verification_matches']) {
                $debugInfo['comparison']['discrepancies'][] = 'Estado de verificación no coincide';
            }
            
            if (!$debugInfo['comparison']['eligibility_matches']) {
                $debugInfo['comparison']['discrepancies'][] = 'Elegibilidad para pago no coincide';
            }
            
            Log::info('=== DEBUG: RASTREO COMPLETADO ===', [
                'commission_id' => $commissionId,
                'total_queries' => count($debugInfo['database_queries']),
                'verification_type' => $debugInfo['verification_process']['type'] ?? 'auto_verified',
                'discrepancies_found' => count($debugInfo['comparison']['discrepancies'])
            ]);
            
            return response()->json([
                'success' => true,
                'commission_id' => $commissionId,
                'debug_info' => $debugInfo,
                'summary' => [
                    'total_database_queries' => count($debugInfo['database_queries']),
                    'verification_required' => $commission->requires_client_payment_verification,
                    'has_payment_schedule' => $hasPaymentSchedule,
                    'accounts_receivable_count' => $accountsReceivable->count(),
                    'existing_verifications_count' => $existingVerifications->count(),
                    'discrepancies_found' => count($debugInfo['comparison']['discrepancies'])
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('=== DEBUG: ERROR EN RASTREO ===', [
                'commission_id' => $commissionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                 'success' => false,
                 'error' => $e->getMessage(),
                 'commission_id' => $commissionId
             ], 500);
         }
     }

    /**
     * Simula la verificación de pagos para contratos CON cronograma de pagos
     */
    private function simulateVerificationWithSchedule($commission, &$debugInfo)
    {
        // Consultar cronograma de pagos
        $paymentSchedules = \Modules\Sales\Models\PaymentSchedule::where('contract_id', $commission->contract_id)
            ->orderBy('installment_number', 'asc')
            ->get();
        
        $debugInfo['database_queries']['payment_schedules'] = [
            'query' => 'SELECT * FROM payment_schedules WHERE contract_id = ' . $commission->contract_id . ' ORDER BY installment_number ASC',
            'count' => $paymentSchedules->count(),
            'table' => 'payment_schedules',
            'fields_checked' => ['contract_id', 'installment_number', 'due_date', 'amount'],
            'results' => $paymentSchedules->map(function($ps) {
                return [
                    'installment_number' => $ps->installment_number,
                    'due_date' => $ps->due_date,
                    'amount' => $ps->amount
                ];
            })->toArray()
        ];
        
        // Determinar qué cuotas verificar según payment_part
        $installmentsToVerify = [];
        if ($commission->payment_part === 'primera_parte') {
            $installmentsToVerify = [1];
        } elseif ($commission->payment_part === 'segunda_parte') {
            $installmentsToVerify = [2];
        } elseif ($commission->payment_part === 'pago_completo') {
            $installmentsToVerify = [1, 2];
        }
        
        $debugInfo['verification_process']['installments_to_verify'] = $installmentsToVerify;
        $verificationResults = [];
        
        foreach ($installmentsToVerify as $installmentNumber) {
            $schedule = $paymentSchedules->where('installment_number', $installmentNumber)->first();
            if ($schedule) {
                $result = $this->simulateInstallmentVerification($commission, $schedule, $debugInfo);
                $verificationResults[] = $result;
            }
        }
        
        // Determinar resultado final
        $allVerified = collect($verificationResults)->every(function($result) {
            return $result['status'] === 'verified';
        });
        
        $debugInfo['final_result'] = [
            'status' => $allVerified ? 'fully_verified' : 'partially_verified',
            'is_eligible' => $allVerified,
            'verification_details' => $verificationResults
        ];
    }

    /**
     * Simula la verificación de pagos para contratos SIN cronograma de pagos
     */
    private function simulateVerificationWithoutSchedule($commission, $accountsReceivable, &$debugInfo)
    {
        $debugInfo['verification_process']['method'] = 'direct_accounts_receivable_check';
        
        // Determinar qué cuentas por cobrar verificar según payment_part
        $arToVerify = [];
        if ($commission->payment_part === 'primera_parte') {
            $arToVerify = $accountsReceivable->take(1);
        } elseif ($commission->payment_part === 'segunda_parte') {
            $arToVerify = $accountsReceivable->skip(1)->take(1);
        } elseif ($commission->payment_part === 'pago_completo') {
            $arToVerify = $accountsReceivable;
        }
        
        $debugInfo['verification_process']['accounts_to_verify'] = $arToVerify->map(function($ar) {
            return [
                'ar_id' => $ar->ar_id,
                'due_date' => $ar->due_date,
                'original_amount' => $ar->original_amount,
                'paid_amount' => $ar->paid_amount
            ];
        })->toArray();
        
        $verificationResults = [];
        
        foreach ($arToVerify as $ar) {
            // Consultar pagos de clientes para esta cuenta por cobrar
            $customerPayments = CustomerPayment::where('ar_id', $ar->ar_id)
                ->where('payment_date', '<=', now())
                ->get();
            
            $debugInfo['database_queries']['customer_payments_ar_' . $ar->ar_id] = [
                'query' => 'SELECT * FROM customer_payments WHERE ar_id = ' . $ar->ar_id . ' AND payment_date <= NOW()',
                'count' => $customerPayments->count(),
                'table' => 'customer_payments',
                'fields_checked' => ['ar_id', 'payment_date', 'amount', 'payment_method'],
                'results' => $customerPayments->map(function($cp) {
                    return [
                        'payment_id' => $cp->payment_id ?? 'N/A',
                        'amount' => $cp->amount,
                        'payment_date' => $cp->payment_date,
                        'payment_method' => $cp->payment_method ?? 'N/A'
                    ];
                })->toArray()
            ];
            
            $totalPaid = $customerPayments->sum('amount');
            $isFullyPaid = $totalPaid >= $ar->original_amount;
            
            $verificationResults[] = [
                'ar_id' => $ar->ar_id,
                'original_amount' => $ar->original_amount,
                'total_paid' => $totalPaid,
                'is_fully_paid' => $isFullyPaid,
                'status' => $isFullyPaid ? 'verified' : 'pending'
            ];
        }
        
        // Determinar resultado final
        $allVerified = collect($verificationResults)->every(function($result) {
            return $result['status'] === 'verified';
        });
        
        $debugInfo['final_result'] = [
            'status' => $allVerified ? 'fully_verified' : 'partially_verified',
            'is_eligible' => $allVerified,
            'verification_details' => $verificationResults
        ];
    }

    /**
     * Simula la verificación de una cuota específica del cronograma
     */
    private function simulateInstallmentVerification($commission, $schedule, &$debugInfo)
    {
        // Buscar cuenta por cobrar correspondiente a esta cuota
        $accountReceivable = AccountReceivable::where('contract_id', $commission->contract_id)
            ->where('due_date', $schedule->due_date)
            ->first();
        
        if (!$accountReceivable) {
            return [
                'installment_number' => $schedule->installment_number,
                'status' => 'no_ar_found',
                'message' => 'No se encontró cuenta por cobrar para esta cuota'
            ];
        }
        
        // Consultar pagos de clientes para esta cuenta por cobrar
        $customerPayments = CustomerPayment::where('ar_id', $accountReceivable->ar_id)
            ->where('payment_date', '<=', now())
            ->get();
        
        $debugInfo['database_queries']['customer_payments_installment_' . $schedule->installment_number] = [
            'query' => 'SELECT * FROM customer_payments WHERE ar_id = ' . $accountReceivable->ar_id . ' AND payment_date <= NOW()',
            'count' => $customerPayments->count(),
            'table' => 'customer_payments',
            'fields_checked' => ['ar_id', 'payment_date', 'amount', 'payment_method'],
            'installment_number' => $schedule->installment_number,
            'results' => $customerPayments->map(function($cp) {
                return [
                    'payment_id' => $cp->payment_id ?? 'N/A',
                    'amount' => $cp->amount,
                    'payment_date' => $cp->payment_date,
                    'payment_method' => $cp->payment_method ?? 'N/A'
                ];
            })->toArray()
        ];
        
        $totalPaid = $customerPayments->sum('amount');
        $isFullyPaid = $totalPaid >= $schedule->amount;
        
        return [
            'installment_number' => $schedule->installment_number,
            'scheduled_amount' => $schedule->amount,
            'total_paid' => $totalPaid,
            'is_fully_paid' => $isFullyPaid,
            'status' => $isFullyPaid ? 'verified' : 'pending',
            'ar_id' => $accountReceivable->ar_id
        ];
    }
    
    /**
     * Endpoint simple para buscar comisiones en estado 'generated' o 'partially_paid'
     * que puedan ser usadas para probar el proceso de pago
     */
    public function debugTestPayPart($commission_id = null)
    {
        Log::info('=== DEBUG FIND TESTABLE COMMISSIONS ===', [
            'commission_id' => $commission_id,
            'timestamp' => now()->toDateTimeString()
        ]);
        
        try {
            // Si se proporciona un commission_id específico, verificar solo esa comisión
            if ($commission_id) {
                $commission = Commission::where('commission_id', $commission_id)->first();
                
                if (!$commission) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Comisión no encontrada',
                        'commission_id' => $commission_id
                    ]);
                }
                
                return response()->json([
                    'success' => true,
                    'commission' => [
                        'commission_id' => $commission->commission_id,
                        'status' => $commission->status,
                        'payment_part' => $commission->payment_part,
                        'is_eligible_for_payment' => $commission->is_eligible_for_payment,
                        'requires_client_payment_verification' => $commission->requires_client_payment_verification,
                        'payment_verification_status' => $commission->payment_verification_status,
                        'can_test_first_payment' => ($commission->status === 'generated' && $commission->payment_part == 1),
                        'can_test_second_payment' => ($commission->status === 'partially_paid' && $commission->payment_part == 2)
                    ]
                ]);
            }
            
            // Buscar comisiones que puedan ser usadas para testing
            $testableCommissions = Commission::whereIn('status', ['generated', 'partially_paid'])
                ->select('commission_id', 'status', 'payment_part', 'is_eligible_for_payment', 
                        'requires_client_payment_verification', 'payment_verification_status')
                ->limit(5)
                ->get();
            
            $results = [];
            foreach ($testableCommissions as $commission) {
                $results[] = [
                    'commission_id' => $commission->commission_id,
                    'status' => $commission->status,
                    'payment_part' => $commission->payment_part,
                    'is_eligible_for_payment' => $commission->is_eligible_for_payment,
                    'requires_client_payment_verification' => $commission->requires_client_payment_verification,
                    'payment_verification_status' => $commission->payment_verification_status,
                    'can_test_first_payment' => ($commission->status === 'generated' && $commission->payment_part == 1),
                    'can_test_second_payment' => ($commission->status === 'partially_paid' && $commission->payment_part == 2),
                    'recommended_for_testing' => (
                        ($commission->status === 'generated' && $commission->payment_part == 1) ||
                        ($commission->status === 'partially_paid' && $commission->payment_part == 2)
                    )
                ];
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Comisiones encontradas para testing',
                'total_found' => count($results),
                'commissions' => $results,
                'sql_query_executed' => "SELECT commission_id, status, payment_part, is_eligible_for_payment, requires_client_payment_verification, payment_verification_status FROM commissions WHERE status IN ('generated', 'partially_paid') LIMIT 5"
            ]);
            
        } catch (\Exception $e) {
            Log::error('ERROR en debugTestPayPart', [
                'commission_id' => $commission_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                 'success' => false,
                 'error' => 'Error interno: ' . $e->getMessage(),
                 'commission_id' => $commission_id
             ], 500);
         }
     }
     
     /**
      * Endpoint de debugging para resetear una comisión a estado approved
      * Permite probar el proceso de pago desde el inicio
      */
     public function debugResetToApproved($commission_id)
     {
         Log::info('=== DEBUG RESET TO APPROVED INICIADO ===', [
             'commission_id' => $commission_id,
             'timestamp' => now()->toDateTimeString()
         ]);
         
         try {
             // Buscar la comisión
             $commission = Commission::where('commission_id', $commission_id)->first();
             
             if (!$commission) {
                 return response()->json([
                     'success' => false,
                     'error' => 'Comisión no encontrada',
                     'commission_id' => $commission_id
                 ]);
             }
             
             $previousStatus = $commission->status;
             $previousEligibility = $commission->is_eligible_for_payment;
             
             // Resetear la comisión a estado approved
             $commission->status = 'approved';
             $commission->is_eligible_for_payment = true;
             $commission->payment_verification_status = null;
             $commission->save();
             
             Log::info('DEBUG: Comisión reseteada', [
                 'commission_id' => $commission->commission_id,
                 'previous_status' => $previousStatus,
                 'new_status' => $commission->status,
                 'previous_eligibility' => $previousEligibility,
                 'new_eligibility' => $commission->is_eligible_for_payment
             ]);
             
             return response()->json([
                 'success' => true,
                 'message' => 'Comisión reseteada a estado approved exitosamente',
                 'commission_id' => $commission->commission_id,
                 'changes' => [
                     'previous_status' => $previousStatus,
                     'new_status' => $commission->status,
                     'previous_eligibility' => $previousEligibility,
                     'new_eligibility' => $commission->is_eligible_for_payment
                 ]
             ]);
             
         } catch (\Exception $e) {
             Log::error('ERROR en debugResetToApproved', [
                 'commission_id' => $commission_id,
                 'error' => $e->getMessage(),
                 'trace' => $e->getTraceAsString()
             ]);
             
             return response()->json([
                 'success' => false,
                 'error' => 'Error interno: ' . $e->getMessage(),
                 'commission_id' => $commission_id
             ], 500);
          }
      }
      
      /**
       * Endpoint de debugging para buscar comisiones en estado approved
       */
      public function debugFindApprovedCommissions()
      {
          try {
              $approvedCommissions = Commission::where('status', 'approved')
                  ->select('commission_id', 'contract_id', 'payment_part', 'status', 'is_eligible_for_payment', 'payment_verification_status')
                  ->limit(10)
                  ->get();
              
              return response()->json([
                  'success' => true,
                  'count' => $approvedCommissions->count(),
                  'commissions' => $approvedCommissions
              ]);
              
          } catch (\Exception $e) {
              return response()->json([
                  'success' => false,
                  'error' => 'Error interno: ' . $e->getMessage()
              ], 500);
          }
      }
}