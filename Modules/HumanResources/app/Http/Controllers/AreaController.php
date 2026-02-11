<?php

namespace Modules\HumanResources\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\HumanResources\Models\Area;

class AreaController extends Controller
{
    /**
     * Display a listing of areas.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Area::query();

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Search by name
        if ($request->has('search')) {
            $search = mb_strtolower($request->search);
            $query->where('name_normalized', 'like', "%{$search}%");
        }

        $areas = $query->withCount(['employees' => function ($q) {
            $q->where('employment_status', 'activo');
        }])->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $areas,
        ]);
    }

    /**
     * Store a newly created area.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:areas,code',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        // Check for duplicate name (case-insensitive)
        $normalized = mb_strtolower(trim($validated['name']));
        if (Area::where('name_normalized', $normalized)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un área con ese nombre.',
            ], 422);
        }

        $area = Area::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Área creada exitosamente.',
            'data' => $area,
        ], 201);
    }

    /**
     * Display the specified area.
     */
    public function show(Area $area): JsonResponse
    {
        $area->loadCount(['employees' => function ($q) {
            $q->where('employment_status', 'activo');
        }]);

        return response()->json([
            'success' => true,
            'data' => $area,
        ]);
    }

    /**
     * Update the specified area.
     */
    public function update(Request $request, Area $area): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:areas,code,' . $area->area_id . ',area_id',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        // Check for duplicate name (case-insensitive), excluding current
        $normalized = mb_strtolower(trim($validated['name']));
        if (Area::where('name_normalized', $normalized)
            ->where('area_id', '!=', $area->area_id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un área con ese nombre.',
            ], 422);
        }

        $area->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Área actualizada exitosamente.',
            'data' => $area,
        ]);
    }

    /**
     * Remove the specified area.
     */
    public function destroy(Area $area): JsonResponse
    {
        // Check if area has employees
        if ($area->employees()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el área porque tiene empleados asignados.',
            ], 422);
        }

        $area->delete();

        return response()->json([
            'success' => true,
            'message' => 'Área eliminada exitosamente.',
        ]);
    }
}
