<?php

namespace Modules\HumanResources\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HumanResources\Repositories\PayrollRepository;
use Modules\HumanResources\Services\PayrollService;
use Modules\HumanResources\Transformers\PayrollResource;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollRepository $payrollRepo,
        protected PayrollService $payrollService,
        protected NotificationService $notificationService
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
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer|exists:employees,employee_id',
            'employee_id' => 'nullable|integer|exists:employees,employee_id', // Backward compatibility
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030',
            'pay_date' => 'required|date',
            'include_commissions' => 'boolean',
            'include_bonuses' => 'boolean',
            'include_overtime' => 'boolean'
        ]);

        try {
            // Determinar los IDs de empleados a procesar
            $employeeIds = $request->employee_ids ?? ($request->employee_id ? [$request->employee_id] : null);

            if ($employeeIds) {
                // Generar nóminas para empleados específicos (BATCH)
                $result = $this->payrollService->generatePayrollBatch(
                    $employeeIds,
                    $request->month,
                    $request->year,
                    $request->pay_date,
                    $request->boolean('include_commissions', true),
                    $request->boolean('include_bonuses', true),
                    $request->boolean('include_overtime', true)
                );

                // Notificar a cada empleado cuya nómina se generó exitosamente
                $this->notifyEmployeesPayroll($result['success'], $request->month, $request->year);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'payrolls' => PayrollResource::collection(collect($result['success'])->map(fn($p) => $p->load(['employee.user']))),
                        'successful' => count($result['success']),
                        'failed' => count($result['failed']),
                        'errors' => $result['failed']
                    ],
                    'message' => count($result['success']) > 0 
                        ? "Se generaron {$result['successful_count']} nóminas exitosamente" 
                        : 'No se pudieron generar las nóminas'
                ], count($result['success']) > 0 ? 200 : 400);
            } else {
                // Generar para TODOS los empleados
                $payrolls = $this->payrollService->generatePayrollForAllEmployees(
                    $request->month,
                    $request->year
                );

                // Notificar a todos los empleados
                $this->notifyEmployeesPayroll($payrolls, $request->month, $request->year);

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

    /**
     * Notificar a los empleados que su nómina fue generada
     */
    private function notifyEmployeesPayroll(array $payrolls, int $month, int $year): void
    {
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $monthName = $months[$month] ?? $month;

        foreach ($payrolls as $payroll) {
            try {
                $employee = $payroll->employee;
                if (!$employee || !$employee->user_id) {
                    continue;
                }

                $netSalary = number_format($payroll->net_salary, 2, '.', ',');

                $this->notificationService->create([
                    'user_id'        => $employee->user_id,
                    'type'           => 'success',
                    'priority'       => 'high',
                    'title'          => "Nómina {$monthName} {$year} Generada",
                    'message'        => "Tu nómina del período {$monthName} {$year} ha sido generada. Salario neto: S/ {$netSalary}. Revisa los detalles en tu portal.",
                    'related_module' => 'payroll',
                    'related_id'     => $payroll->payroll_id ?? $payroll->id,
                    'related_url'    => '/hr/payroll',
                    'icon'           => 'dollar-sign',
                ]);
            } catch (\Exception $e) {
                // No interrumpir el flujo si falla una notificación
                \Log::warning("No se pudo notificar al empleado {$payroll->employee_id}: " . $e->getMessage());
            }
        }
    }
}