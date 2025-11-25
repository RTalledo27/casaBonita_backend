<?php

namespace Modules\HumanResources\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\HumanResources\Models\CommissionScheme;

class CommissionSchemeController extends Controller
{
    public function index(): JsonResponse
    {
        $schemes = CommissionScheme::with('rules')->orderBy('is_default', 'desc')->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $schemes]);
    }

    public function show(int $id): JsonResponse
    {
        $scheme = CommissionScheme::with('rules')->find($id);
        if (!$scheme) return response()->json(['success' => false, 'message' => 'Scheme not found'], 404);
        return response()->json(['success' => true, 'data' => $scheme]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_default' => 'sometimes|boolean'
        ]);

        $scheme = CommissionScheme::create($validated);
        return response()->json(['success' => true, 'data' => $scheme], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $scheme = CommissionScheme::find($id);
        if (!$scheme) return response()->json(['success' => false, 'message' => 'Scheme not found'], 404);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_default' => 'sometimes|boolean'
        ]);

        $scheme->update($validated);
        return response()->json(['success' => true, 'data' => $scheme]);
    }

    public function destroy(int $id): JsonResponse
    {
        $scheme = CommissionScheme::find($id);
        if (!$scheme) return response()->json(['success' => false, 'message' => 'Scheme not found'], 404);
        $scheme->delete();
        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
}
