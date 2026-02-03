<?php

namespace Modules\ServiceDesk\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\ServiceDesk\Models\SlaConfig;

class SlaConfigController extends Controller
{
    /**
     * Get all SLA configurations
     */
    public function index(): JsonResponse
    {
        $configs = SlaConfig::orderByRaw("
            CASE priority 
                WHEN 'critica' THEN 1 
                WHEN 'alta' THEN 2 
                WHEN 'media' THEN 3 
                WHEN 'baja' THEN 4 
            END
        ")->get();

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Update SLA configuration for a priority
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'response_hours' => 'required|integer|min:1',
            'resolution_hours' => 'required|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        $config = SlaConfig::findOrFail($id);
        $config->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'SLA configuration updated successfully',
            'data' => $config,
        ]);
    }

    /**
     * Bulk update SLA configurations
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'configs' => 'required|array',
            'configs.*.id' => 'required|exists:sla_configs,id',
            'configs.*.response_hours' => 'required|integer|min:1',
            'configs.*.resolution_hours' => 'required|integer|min:1',
        ]);

        foreach ($validated['configs'] as $configData) {
            SlaConfig::where('id', $configData['id'])->update([
                'response_hours' => $configData['response_hours'],
                'resolution_hours' => $configData['resolution_hours'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'SLA configurations updated successfully',
        ]);
    }
}
