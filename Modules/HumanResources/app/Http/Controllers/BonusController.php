<?php

namespace Modules\HumanResources\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HumanResources\Http\Requests\storeBonusRequest;
use Modules\HumanResources\Repositories\BonusRepository;
use Modules\HumanResources\Services\BonusService;
use Modules\HumanResources\Transformers\BonusResource;

class BonusController extends Controller
{
    public function __construct(
        protected BonusRepository $bonusRepo,
        protected BonusService $bonusService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'employee_id',
            'bonus_type_id',
            'payment_status',
            'period_month',
            'period_year',
            'requires_approval'
        ]);

        if ($request->has('paginate') && $request->paginate === 'true') {
            $bonuses = $this->bonusRepo->getPaginated($filters, $request->get('per_page', 15));
            return response()->json([
                'success' => true,
                'data' => BonusResource::collection($bonuses->items()),
                'meta' => [
                    'current_page' => $bonuses->currentPage(),
                    'last_page' => $bonuses->lastPage(),
                    'per_page' => $bonuses->perPage(),
                    'total' => $bonuses->total()
                ],
                'message' => 'Bonos obtenidos exitosamente'
            ]);
        } else {
            $bonuses = $this->bonusRepo->getAll($filters);
            return response()->json([
                'success' => true,
                'data' => BonusResource::collection($bonuses),
                'message' => 'Bonos obtenidos exitosamente'
            ]);
        }
    }

    public function show(int $id): JsonResponse
    {
        $bonus = $this->bonusRepo->findById($id);

        if (!$bonus) {
            return response()->json([
                'success' => false,
                'message' => 'Bono no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new BonusResource($bonus),
            'message' => 'Bono obtenido exitosamente'
        ]);
    }

    public function store(storeBonusRequest $request): JsonResponse
    {
        try {
            $bonus = $this->bonusService->createSpecialBonus(
                $request->employee_id,
                $request->bonus_amount,
                $request->description,
                auth()->user()->employee->employee_id
            );

            return response()->json([
                'success' => true,
                'data' => new BonusResource($bonus),
                'message' => 'Bono creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear bono: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar bonos automáticos para un período
     */
    public function processAutomaticBonuses(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030'
        ]);

        try {
            $bonuses = $this->bonusService->processAllAutomaticBonuses(
                $request->month,
                $request->year
            );

            $totalBonuses = array_sum(array_map('count', $bonuses));

            return response()->json([
                'success' => true,
                'data' => array_map(function ($bonusGroup) {
                    return BonusResource::collection($bonusGroup);
                }, $bonuses),
                'summary' => [
                    'total_bonuses_created' => $totalBonuses,
                    'by_type' => array_map('count', $bonuses)
                ],
                'message' => "Procesados {$totalBonuses} bonos automáticos exitosamente"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar bonos automáticos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function dashboardBonuses(Request $request): JsonResponse
    {
        $employeeId = $request->user()->employee->employee_id;
        $data = $this->bonusService->getBonusesForDashboard($employeeId);

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Bonos para dashboard obtenidos exitosamente'
        ]);
    }
}
