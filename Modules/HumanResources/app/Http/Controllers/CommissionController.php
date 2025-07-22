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
        $filters = $request->only(['employee_id', 'payment_status', 'period_month', 'period_year']);

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
}