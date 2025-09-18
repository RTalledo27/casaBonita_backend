<?php

namespace Modules\HumanResources\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\HumanResources\Repositories\CommissionRepository;
use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Services\CommissionVerificationService;
use Modules\HumanResources\Services\CommissionPaymentVerificationService;
use Modules\HumanResources\Transformers\CommissionResource;
use Exception;

class CommissionController extends Controller
{
    public function __construct(
        protected CommissionRepository $commissionRepo,
        protected CommissionService $commissionService,
        protected CommissionVerificationService $verificationService,
        protected CommissionPaymentVerificationService $paymentVerificationService
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
            // Buscar la comisión específica
            $commission = $this->commissionRepo->findById($commissionId);
            
            if (!$commission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comisión no encontrada'
                ], 404);
            }

            // Verificar que la comisión corresponda al payment_part solicitado
            if ($commission->payment_part !== $request->payment_part) {
                return response()->json([
                    'success' => false,
                    'message' => 'La parte de pago no coincide con la comisión solicitada'
                ], 400);
            }

            // Verificar que la comisión no esté ya pagada
            if ($commission->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta parte de la comisión ya ha sido pagada'
                ], 400);
            }

            // Verificar que el cliente haya pagado la cuota correspondiente
            $verificationResult = $this->paymentVerificationService->verifyClientPayments($commission);
            
            $canPay = false;
            if ($request->payment_part === 1) {
                $canPay = $verificationResult['first_payment'] ?? false;
            } elseif ($request->payment_part === 2) {
                $canPay = $verificationResult['second_payment'] ?? false;
            }

            if (!$canPay) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede pagar esta parte de la comisión porque el cliente no ha pagado la cuota correspondiente',
                    'verification_details' => $verificationResult
                ], 400);
            }

            // Proceder con el pago
            $success = $this->commissionService->payCommissions([$commissionId]);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Parte de la comisión pagada exitosamente',
                    'commission_id' => $commissionId,
                    'payment_part' => $request->payment_part,
                    'amount' => $commission->commission_amount
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo procesar el pago de la comisión'
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
            $commissions = $this->commissionRepo->getCommissionsForAutomaticVerification($limit);
            
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
}