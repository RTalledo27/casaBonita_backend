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

class HumanResourcesController extends Controller
{
    public function __construct(
        protected EmployeeRepository $employeeRepo,
        protected CommissionService $commissionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['employee_type', 'team_id', 'status']);
        $employees = $this->employeeRepo->getAll($filters);

        return response()->json([
            'success' => true,
            'data' => EmployeeResource::collection($employees),
            'message' => 'Empleados obtenidos exitosamente'
        ]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->employeeRepo->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new EmployeeResource($employee),
            'message' => 'Empleado creado exitosamente'
        ], 201);
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
            'data' => new EmployeeResource($employee),
            'message' => 'Empleado obtenido exitosamente'
        ]);
    }

    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        $employee = $this->employeeRepo->update($id, $request->validated());

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Empleado no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new EmployeeResource($employee),
            'message' => 'Empleado actualizado exitosamente'
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
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
