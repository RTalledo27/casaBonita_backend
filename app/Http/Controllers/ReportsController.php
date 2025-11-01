<?php

namespace App\Http\Controllers;

use App\Services\ReportsService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ReportsController extends Controller
{
    protected $reportsService;
    protected $exportService;

    public function __construct(ReportsService $reportsService, ExportService $exportService)
    {
        $this->reportsService = $reportsService;
        $this->exportService = $exportService;
    }

    /**
     * Get dashboard metrics and summary data
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['start_date', 'end_date', 'office_id']);
            $dashboardData = $this->reportsService->getDashboardMetrics($filters);

            return response()->json([
                'success' => true,
                'data' => $dashboardData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener métricas del dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export report to specified format
     */
    public function export(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:sales,payments,projections',
            'format' => 'required|in:excel,pdf,csv',
            'filters' => 'required|array',
            'template_id' => 'nullable|integer|exists:report_templates,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $exportData = $this->exportService->generateReport(
                $request->report_type,
                $request->format,
                $request->filters,
                $request->template_id
            );

            return response()->json([
                'success' => true,
                'data' => $exportData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get report templates
     */
    public function templates(Request $request): JsonResponse
    {
        try {
            $type = $request->get('type');
            $templates = $this->reportsService->getTemplates($type);

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener plantillas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get generated reports history
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['user_id', 'format', 'status', 'start_date', 'end_date']);
            $history = $this->reportsService->getReportsHistory($filters);

            return response()->json([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial de reportes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}