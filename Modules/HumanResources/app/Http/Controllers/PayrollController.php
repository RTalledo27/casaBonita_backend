<?php

namespace Modules\HumanResources\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HumanResources\Repositories\PayrollRepository;
use Modules\HumanResources\Services\PayrollService;
use Modules\HumanResources\Transformers\PayrollResource;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollRepository $payrollRepo,
        protected PayrollService $payrollService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['period', 'status', 'employee_id']);

        if ($request->has('paginate') && $request->paginate === 'true') {
            $payrolls = $this->payrollRepo->getPaginated($filters, $request->get('per_page', 15));
            return response()->json([
                'success' => true,
                'data' => PayrollResource::collection($payrolls->items()),
                'meta' => [
                    'current_page' => $payrolls->currentPage(),
                    'last_page' => $payrolls->lastPage(),
                    'per_page' => $payrolls->perPage(),
                    'total' => $payrolls->total()
                ],
                'message' => 'Nóminas obtenidas exitosamente'
            ]);
        } else {
            $payrolls = $this->payrollRepo->getAll($filters);
            return response()->json([
                'success' => true,
                'data' => PayrollResource::collection($payrolls),
                'message' => 'Nóminas obtenidas exitosamente'
            ]);
        }
    }

    public function show(int $id): JsonResponse
    {
        $payroll = $this->payrollRepo->findById($id);

        if (!$payroll) {
            return response()->json([
                'success' => false,
                'message' => 'Nómina no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PayrollResource($payroll),
            'message' => 'Nómina obtenida exitosamente'
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'nullable|integer|exists:employees,employee_id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030'
        ]);

        try {
            if ($request->employee_id) {
                $payroll = $this->payrollService->generatePayrollForEmployee(
                    $request->employee_id,
                    $request->month,
                    $request->year
                );

                return response()->json([
                    'success' => true,
                    'data' => new PayrollResource($payroll->load(['employee.user'])),
                    'message' => 'Nómina generada exitosamente'
                ]);
            } else {
                $payrolls = $this->payrollService->generatePayrollForAllEmployees(
                    $request->month,
                    $request->year
                );

                return response()->json([
                    'success' => true,
                    'data' => PayrollResource::collection(collect($payrolls)->map(fn($p) => $p->load(['employee.user']))),
                    'message' => 'Nóminas generadas exitosamente',
                    'count' => count($payrolls)
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar nómina: ' . $e->getMessage()
            ], 400);
        }
    }

    public function process(int $id): JsonResponse
    {
        try {
            $processed = $this->payrollService->processPayroll($id, auth()->user()->employee->employee_id);

            if (!$processed) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo procesar la nómina'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Nómina procesada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar nómina: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approve(int $id): JsonResponse
    {
        try {
            $approved = $this->payrollService->approvePayroll($id, auth()->user()->employee->employee_id);

            if (!$approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo aprobar la nómina'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Nómina aprobada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar nómina: ' . $e->getMessage()
            ], 500);
        }
    }
}
