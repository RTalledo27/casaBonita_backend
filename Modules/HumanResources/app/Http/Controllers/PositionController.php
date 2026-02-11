<?php

namespace Modules\HumanResources\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\HumanResources\Models\Position;

class PositionController extends Controller
{
    /**
     * Display a listing of positions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Position::query();

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $search = mb_strtolower($request->search);
            $query->where('name_normalized', 'like', "%{$search}%");
        }

        $positions = $query->withCount(['employees' => function ($q) {
            $q->where('employment_status', 'activo');
        }])->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $positions,
        ]);
    }

    /**
     * Store a newly created position.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'category' => 'required|in:ventas,admin,tech,gerencia,operaciones',
            'is_commission_eligible' => 'boolean',
            'is_bonus_eligible' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $normalized = mb_strtolower(trim($validated['name']));
        if (Position::where('name_normalized', $normalized)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un cargo con ese nombre.',
            ], 422);
        }

        $position = Position::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cargo creado exitosamente.',
            'data' => $position,
        ], 201);
    }

    /**
     * Display the specified position.
     */
    public function show(Position $position): JsonResponse
    {
        $position->loadCount(['employees' => function ($q) {
            $q->where('employment_status', 'activo');
        }]);

        return response()->json([
            'success' => true,
            'data' => $position,
        ]);
    }

    /**
     * Update the specified position.
     */
    public function update(Request $request, Position $position): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'category' => 'required|in:ventas,admin,tech,gerencia,operaciones',
            'is_commission_eligible' => 'boolean',
            'is_bonus_eligible' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $normalized = mb_strtolower(trim($validated['name']));
        if (Position::where('name_normalized', $normalized)
            ->where('position_id', '!=', $position->position_id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un cargo con ese nombre.',
            ], 422);
        }

        $position->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cargo actualizado exitosamente.',
            'data' => $position,
        ]);
    }

    /**
     * Remove the specified position.
     */
    public function destroy(Position $position): JsonResponse
    {
        if ($position->employees()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el cargo porque tiene empleados asignados.',
            ], 422);
        }

        $position->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cargo eliminado exitosamente.',
        ]);
    }
}
