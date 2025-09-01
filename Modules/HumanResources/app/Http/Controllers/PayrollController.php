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
        $filters = $request->only(['period', 'status', 'employee_id', 'search', 'page', 'per_page']);

        if ($request->has('paginate') && $request->paginate === 'true') {
            $payrolls = $this->payrollRepo->getPaginated($filters, $request->get('per_page', 15));
            $globalTotals = $this->payrollRepo->getGlobalTotals($filters);
            
            return response()->json([
                'success' => true,
                'data' => PayrollResource::collection($payrolls->items()),
                'meta' => [
                    'current_page' => $payrolls->currentPage(),
                    'last_page' => $payrolls->lastPage(),
                    'per_page' => $payrolls->perPage(),
                    'total' => $payrolls->total()
                ],
                'totals' => $globalTotals,
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

    public function show(string $id): JsonResponse
    {
        $payroll = $this->payrollRepo->findById((int) $id);

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

    public function process(string $id): JsonResponse
    {
        try {
            $processed = $this->payrollService->processPayroll((int) $id, auth()->user()->employee->employee_id);

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

    public function approve(string $id): JsonResponse
    {
        try {
            $approved = $this->payrollService->approvePayroll((int) $id, auth()->user()->employee->employee_id);

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

    public function processBulk(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'status' => 'nullable|string|in:borrador,pendiente'
        ]);

        try {
            $processed = $this->payrollService->processBulkPayrolls(
                $request->period,
                $request->status ?? 'borrador',
                auth()->user()->employee->employee_id
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'processed_count' => $processed['count'],
                    'processed_payrolls' => PayrollResource::collection($processed['payrolls'])
                ],
                'message' => "Se procesaron {$processed['count']} nóminas exitosamente"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar nóminas: ' . $e->getMessage()
            ], 500);
        }
    }
}
