<?php

namespace Modules\HumanResources\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\HumanResources\Models\Office;

class OfficeController extends Controller
{
    /**
     * Display a listing of offices.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Office::query();

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Search by name
        if ($request->has('search')) {
            $search = mb_strtolower($request->search);
            $query->where('name_normalized', 'like', "%{$search}%");
        }

        $offices = $query->withCount(['employees' => function ($q) {
            $q->where('employment_status', 'activo');
        }])->with('teams')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $offices,
        ]);
    }

    /**
     * Store a newly created office.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:offices,code',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'monthly_goal' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Check for duplicate name (case-insensitive)
        $normalized = mb_strtolower(trim($validated['name']));
        if (Office::where('name_normalized', $normalized)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una oficina con ese nombre.',
            ], 422);
        }

        $office = Office::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Oficina creada exitosamente.',
            'data' => $office,
        ], 201);
    }

    /**
     * Display the specified office.
     */
    public function show(Office $office): JsonResponse
    {
        $office->loadCount(['employees' => function ($q) {
            $q->where('employment_status', 'activo');
        }]);

        return response()->json([
            'success' => true,
            'data' => $office,
        ]);
    }

    /**
     * Update the specified office.
     */
    public function update(Request $request, Office $office): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:offices,code,' . $office->office_id . ',office_id',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'monthly_goal' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Check for duplicate name (case-insensitive), excluding current
        $normalized = mb_strtolower(trim($validated['name']));
        if (Office::where('name_normalized', $normalized)
            ->where('office_id', '!=', $office->office_id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una oficina con ese nombre.',
            ], 422);
        }

        $office->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Oficina actualizada exitosamente.',
            'data' => $office,
        ]);
    }

    /**
     * Remove the specified office.
     */
    public function destroy(Office $office): JsonResponse
    {
        // Check if office has employees
        if ($office->employees()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la oficina porque tiene empleados asignados.',
            ], 422);
        }

        $office->delete();

        return response()->json([
            'success' => true,
            'message' => 'Oficina eliminada exitosamente.',
        ]);
    }
}
