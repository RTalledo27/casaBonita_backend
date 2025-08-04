<?php

namespace Modules\HumanResources\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HumanResources\Repositories\CommissionRepository;
use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Transformers\CommissionResource;

class CommissionController extends Controller
{
    public function __construct(
        protected CommissionRepository $commissionRepo,
        protected CommissionService $commissionService
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
}