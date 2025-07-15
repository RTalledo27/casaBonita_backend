<?php

namespace Modules\HumanResources\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HumanResources\Http\Requests\StoreEmployeeRequest;
use Modules\HumanResources\Http\Requests\UpdateEmployeeRequest;
use Modules\HumanResources\Repositories\EmployeeRepository;
use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Transformers\EmployeeResource;

class EmployeeController extends Controller
{
    //
    public function __construct(
        protected EmployeeRepository $employeeRepo,
        protected CommissionService $commissionService
    ) {}
    
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['employee_type', 'team_id', 'status', 'search']);
        if ($request->has('paginate') && $request->paginate === 'true') {
            $employees = $this->employeeRepo->getPaginated($filters, $request->get('per_page', 15));
            return response()->json([
                'success' => true,
                'data' => EmployeeResource::collection($employees->items()),
                'meta' => [
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                    'per_page' => $employees->perPage(),
                    'total' => $employees->total()
                ],
                'message' => 'Empleados obtenidos exitosamente'
            ]);
        } else {
            $employees = $this->employeeRepo->getAll($filters);
            return response()->json([
                'success' => true,
                'data' => EmployeeResource::collection($employees),
                'message' => 'Empleados obtenidos exitosamente'
            ]);
        }
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Generar código de empleado si no se proporciona
            if (!isset($data['employee_code'])) {
                $data['employee_code'] = $this->employeeRepo->generateEmployeeCode();
            }

            $employee = $this->employeeRepo->create($data);

            return response()->json([
                'success' => true,
                'data' => new EmployeeResource($employee->load(['user', 'team'])),
                'message' => 'Empleado creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $employee = $this->employeeRepo->findById($id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Empleado no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new EmployeeResource($employee->load(['user', 'team', 'currentMonthCommissions', 'currentMonthBonuses'])),
            'message' => 'Empleado obtenido exitosamente'
        ]);
    }

    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        try {
            $employee = $this->employeeRepo->update($id, $request->validated());

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empleado no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new EmployeeResource($employee->load(['user', 'team'])),
                'message' => 'Empleado actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->employeeRepo->delete($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empleado no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Empleado eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function advisors(): JsonResponse
    {
        $advisors = $this->employeeRepo->getAdvisors();

        return response()->json([
            'success' => true,
            'data' => EmployeeResource::collection($advisors),
            'message' => 'Asesores obtenidos exitosamente'
        ]);
    }

    public function dashboard(Request $request, int $id): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        try {
            $dashboard = $this->commissionService->getAdvisorDashboard($id, $month, $year);

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'message' => 'Dashboard obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function topPerformers(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $limit = $request->get('limit', 10);

        $topPerformers = $this->commissionService->getTopPerformers($month, $year, $limit);

        return response()->json([
            'success' => true,
            'data' => EmployeeResource::collection($topPerformers),
            'message' => 'Top performers obtenidos exitosamente'
        ]);
    }
}
