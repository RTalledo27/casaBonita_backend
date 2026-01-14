<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Http\Requests\ManzanaFinancingRuleRequest;
use Modules\Inventory\Models\ManzanaFinancingRule;
use Modules\Inventory\Transformers\ManzanaFinancingRuleResource;

class ManzanaFinancingRuleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inventory.manzanas.view')->only(['index', 'show']);
        $this->middleware('permission:inventory.manzanas.update')->only(['store', 'update', 'destroy']);
    }

    public function index()
    {
        $rules = ManzanaFinancingRule::with('manzana')
            ->get()
            ->sortBy(fn ($r) => $r->manzana?->name ?? '');

        return ManzanaFinancingRuleResource::collection($rules->values());
    }

    public function store(ManzanaFinancingRuleRequest $request)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $rule = ManzanaFinancingRule::updateOrCreate(
                ['manzana_id' => $data['manzana_id']],
                [
                    'financing_type' => $data['financing_type'],
                    'max_installments' => $data['financing_type'] === 'cash_only' ? null : ($data['max_installments'] ?? null),
                    'min_down_payment_percentage' => $data['min_down_payment_percentage'] ?? null,
                    'allows_balloon_payment' => (bool) ($data['allows_balloon_payment'] ?? false),
                    'allows_bpp_bonus' => (bool) ($data['allows_bpp_bonus'] ?? false),
                ]
            );

            DB::commit();

            return (new ManzanaFinancingRuleResource($rule->load('manzana')))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al guardar regla de financiamiento',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(ManzanaFinancingRule $manzanaFinancingRule)
    {
        return new ManzanaFinancingRuleResource($manzanaFinancingRule->load('manzana'));
    }

    public function update(ManzanaFinancingRuleRequest $request, ManzanaFinancingRule $manzanaFinancingRule)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $manzanaFinancingRule->update([
                'financing_type' => $data['financing_type'],
                'max_installments' => $data['financing_type'] === 'cash_only' ? null : ($data['max_installments'] ?? null),
                'min_down_payment_percentage' => $data['min_down_payment_percentage'] ?? null,
                'allows_balloon_payment' => (bool) ($data['allows_balloon_payment'] ?? false),
                'allows_bpp_bonus' => (bool) ($data['allows_bpp_bonus'] ?? false),
            ]);

            DB::commit();

            return new ManzanaFinancingRuleResource($manzanaFinancingRule->fresh()->load('manzana'));
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar regla de financiamiento',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(ManzanaFinancingRule $manzanaFinancingRule)
    {
        try {
            $manzanaFinancingRule->delete();
            return response()->json(['message' => 'Regla eliminada correctamente']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al eliminar regla de financiamiento',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

