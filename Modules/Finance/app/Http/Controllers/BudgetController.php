<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Http\Requests\StoreBudgetRequest;
use Modules\Finance\Http\Requests\UpdateBudgetRequest;
use Modules\Finance\Models\Budget;
use Modules\Finance\Services\BudgetService;
use Modules\Finance\Transformers\BudgetResource;

class BudgetController extends Controller
{
    protected BudgetService $budgetService;

    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Budget::class);

        $filters = $request->only(['fiscal_year', 'status', 'search', 'per_page']);
        $budgets = $this->budgetService->getAllBudgets($filters);

        return response()->json([
            'success' => true,
            'data' => BudgetResource::collection($budgets->items()),
            'meta' => [
                'current_page' => $budgets->currentPage(),
                'last_page' => $budgets->lastPage(),
                'per_page' => $budgets->perPage(),
                'total' => $budgets->total()
            ]
        ]);
    }

    public function store(StoreBudgetRequest $request): JsonResponse
    {
        $this->authorize('store', \Modules\Finance\Models\Budget::class);

        try {
            $budget = $this->budgetService->createBudget($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Presupuesto creado exitosamente',
                'data' => new BudgetResource($budget)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el presupuesto: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $budget = $this->budgetService->getBudgetById($id);

        if (!$budget) {
            return response()->json([
                'success' => false,
                'message' => 'Presupuesto no encontrado'
            ], 404);
        }

        $this->authorize('view', $budget);

        return response()->json([
            'success' => true,
            'data' => new BudgetResource($budget)
        ]);
    }

    public function update(UpdateBudgetRequest $request, int $id): JsonResponse
    {
        $budget = $this->budgetService->getBudgetById($id);

        if (!$budget) {
            return response()->json([
                'success' => false,
                'message' => 'Presupuesto no encontrado'
            ], 404);
        }

        $this->authorize('update', $budget);

        try {
            $budget = $this->budgetService->updateBudget($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Presupuesto actualizado exitosamente',
                'data' => new BudgetResource($budget)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el presupuesto: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $budget = $this->budgetService->getBudgetById($id);

        if (!$budget) {
            return response()->json([
                'success' => false,
                'message' => 'Presupuesto no encontrado'
            ], 404);
        }

        $this->authorize('delete', $budget);

        try {
            $this->budgetService->deleteBudget($id);

            return response()->json([
                'success' => true,
                'message' => 'Presupuesto eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el presupuesto: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approve(int $id): JsonResponse
    {
        $budget = $this->budgetService->getBudgetById($id);

        if (!$budget) {
            return response()->json([
                'success' => false,
                'message' => 'Presupuesto no encontrado'
            ], 404);
        }

        $this->authorize('approve', $budget);

        try {
            $budget = $this->budgetService->approveBudget($id);

            return response()->json([
                'success' => true,
                'message' => 'Presupuesto aprobado exitosamente',
                'data' => new BudgetResource($budget)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar el presupuesto: ' . $e->getMessage()
            ], 500);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \Modules\Finance\Models\Budget::class);

        $fiscalYear = $request->get('fiscal_year', date('Y'));
        $summary = $this->budgetService->getBudgetSummary($fiscalYear);

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }
}
