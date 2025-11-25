<?php

namespace Modules\HumanResources\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\HumanResources\Models\CommissionRule;

class CommissionRuleController extends Controller
{
    public function index(): JsonResponse
    {
        $rules = CommissionRule::with('scheme')->orderBy('scheme_id')->orderBy('priority', 'desc')->get();
        return response()->json(['success' => true, 'data' => $rules]);
    }

    public function show(int $id): JsonResponse
    {
        $rule = CommissionRule::with('scheme')->find($id);
        if (!$rule) return response()->json(['success' => false, 'message' => 'Rule not found'], 404);
        return response()->json(['success' => true, 'data' => $rule]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scheme_id' => 'required|exists:commission_schemes,id',
            'min_sales' => 'required|integer|min:0',
            'max_sales' => 'nullable|integer|min:0',
            'term_group' => 'required|in:short,long,any',
            'sale_type' => 'nullable|in:cash,financed,both',
            'term_min_months' => 'nullable|integer|min:0',
            'term_max_months' => 'nullable|integer|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date',
            'percentage' => 'required|numeric|min:0',
            'priority' => 'sometimes|integer'
        ]);

        // Business validations
        if (!is_null($validated['term_min_months'] ?? null) && !is_null($validated['term_max_months'] ?? null)) {
            if ($validated['term_max_months'] < $validated['term_min_months']) {
                return response()->json(['success' => false, 'message' => 'term_max_months must be >= term_min_months'], 422);
            }
        }

        if (!is_null($validated['effective_from'] ?? null) && !is_null($validated['effective_to'] ?? null)) {
            if (strtotime($validated['effective_to']) < strtotime($validated['effective_from'])) {
                return response()->json(['success' => false, 'message' => 'effective_to must be >= effective_from'], 422);
            }
        }

        $rule = CommissionRule::create($validated);
        return response()->json(['success' => true, 'data' => $rule], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rule = CommissionRule::find($id);
        if (!$rule) return response()->json(['success' => false, 'message' => 'Rule not found'], 404);

        $validated = $request->validate([
            'scheme_id' => 'sometimes|exists:commission_schemes,id',
            'min_sales' => 'sometimes|integer|min:0',
            'max_sales' => 'nullable|integer|min:0',
            'term_group' => 'sometimes|in:short,long,any',
            'sale_type' => 'nullable|in:cash,financed,both',
            'term_min_months' => 'nullable|integer|min:0',
            'term_max_months' => 'nullable|integer|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date',
            'percentage' => 'sometimes|numeric|min:0',
            'priority' => 'sometimes|integer'
        ]);

        // Business validations
        if (!is_null($validated['term_min_months'] ?? null) && !is_null($validated['term_max_months'] ?? null)) {
            if ($validated['term_max_months'] < $validated['term_min_months']) {
                return response()->json(['success' => false, 'message' => 'term_max_months must be >= term_min_months'], 422);
            }
        }

        if (!is_null($validated['effective_from'] ?? null) && !is_null($validated['effective_to'] ?? null)) {
            if (strtotime($validated['effective_to']) < strtotime($validated['effective_from'])) {
                return response()->json(['success' => false, 'message' => 'effective_to must be >= effective_from'], 422);
            }
        }

        $rule->update($validated);
        return response()->json(['success' => true, 'data' => $rule]);
    }

    public function destroy(int $id): JsonResponse
    {
        $rule = CommissionRule::find($id);
        if (!$rule) return response()->json(['success' => false, 'message' => 'Rule not found'], 404);
        $rule->delete();
        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
}
