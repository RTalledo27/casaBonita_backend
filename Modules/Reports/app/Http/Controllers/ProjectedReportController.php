<?php

namespace Modules\Reports\app\Http\Controllers;

use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Reports\app\Services\ProjectedReportService;
use Modules\Reports\app\Services\PaymentScheduleProjectionService;

class ProjectedReportController extends Controller
{
    protected $projectedReportService;
    protected $paymentScheduleProjectionService;

    public function __construct(
        ProjectedReportService $projectedReportService,
        PaymentScheduleProjectionService $paymentScheduleProjectionService
    ) {
        $this->projectedReportService = $projectedReportService;
        $this->paymentScheduleProjectionService = $paymentScheduleProjectionService;
    }

    /**
     * Get all projected reports with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'projection_type' => 'nullable|string|in:financial,sales,revenue,cashflow,collection',
                'period' => 'nullable|string|in:monthly,quarterly,yearly',
                'year' => 'nullable|integer|min:2020|max:2030',
                'scenario' => 'nullable|string|in:optimistic,realistic,pessimistic',
            ]);

            $projections = $this->projectedReportService->getAllProjections($validated);

            return response()->json([
                'success' => true,
                'data' => $projections,
                'message' => 'Proyecciones obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proyecciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get key metrics summary
     */
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'year' => 'nullable|integer|min:2020|max:2030',
                'scenario' => 'nullable|string|in:optimistic,realistic,pessimistic',
            ]);

            $year = $validated['year'] ?? date('Y');
            $scenario = $validated['scenario'] ?? 'realistic';

            $metrics = $this->projectedReportService->getKeyMetrics($year, $scenario);

            return response()->json([
                'success' => true,
                'data' => $metrics,
                'message' => 'Métricas obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener métricas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue projection chart data
     */
    public function getRevenueProjectionChart(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'year' => 'nullable|integer|min:2020|max:2030',
                'months_ahead' => 'nullable|integer|min:1|max:24',
            ]);

            $year = $validated['year'] ?? date('Y');
            $monthsAhead = $validated['months_ahead'] ?? 12;

            $chartData = $this->projectedReportService->getRevenueProjectionChart($year, $monthsAhead);

            return response()->json([
                'success' => true,
                'data' => $chartData,
                'message' => 'Datos de gráfico obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos de gráfico',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales projection chart data
     */
    public function getSalesProjectionChart(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'year' => 'nullable|integer|min:2020|max:2030',
            ]);

            $year = $validated['year'] ?? date('Y');

            $chartData = $this->projectedReportService->getSalesProjectionChart($year);

            return response()->json([
                'success' => true,
                'data' => $chartData,
                'message' => 'Datos de gráfico obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos de gráfico',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cash flow projection chart data
     */
    public function getCashFlowChart(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'year' => 'nullable|integer|min:2020|max:2030',
                'months_ahead' => 'nullable|integer|min:1|max:24',
            ]);

            $year = $validated['year'] ?? date('Y');
            $monthsAhead = $validated['months_ahead'] ?? 12;

            $chartData = $this->projectedReportService->getCashFlowChart($year, $monthsAhead);

            // Registrar actividad
            if ($request->user()) {
                UserActivityLog::log(
                    $request->user()->user_id,
                    UserActivityLog::ACTION_REPORT_VIEWED,
                    "Reporte de flujo de caja visualizado para {$year}",
                    [
                        'report_type' => 'cash_flow',
                        'year' => $year,
                        'months_ahead' => $monthsAhead,
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'data' => $chartData,
                'message' => 'Datos de flujo de caja obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos de flujo de caja',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single projection detail
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $projection = $this->projectedReportService->getProjectionDetail($id);

            if (!$projection) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proyección no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $projection,
                'message' => 'Proyección obtenida exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proyección',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export projected reports to Excel
     */
    public function exportToExcel(Request $request)
    {
        try {
            $validated = $request->validate([
                'year' => 'nullable|integer|min:2020|max:2030',
                'scenario' => 'nullable|string|in:optimistic,realistic,pessimistic',
                'months_ahead' => 'nullable|integer|min:1|max:24',
            ]);

            $year = $validated['year'] ?? date('Y');
            $scenario = $validated['scenario'] ?? 'realistic';
            $monthsAhead = $validated['months_ahead'] ?? 12;

            // Get the export data
            $exportData = $this->projectedReportService->getExportData($year, $scenario, $monthsAhead);

            // Use the ExportService
            $exportService = app(\App\Services\ExportService::class);
            
            $fileName = 'reportes_proyectados_' . $year . '_' . date('YmdHis') . '.xlsx';
            $filePath = $exportService->exportToExcel($exportData, $fileName);

            // Get the full file path - filePath already includes 'exports/'
            $fullPath = storage_path('app/' . $filePath);
            
            \Log::info('Projected export - Looking for file at: ' . $fullPath);
            \Log::info('Projected export - File exists: ' . (file_exists($fullPath) ? 'YES' : 'NO'));
            
            if (!file_exists($fullPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo no encontrado',
                    'debug' => [
                        'filePath' => $filePath,
                        'fullPath' => $fullPath,
                        'fileName' => $fileName
                    ]
                ], 404);
            }

            // Return file download response
            return response()->download($fullPath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Cache-Control' => 'no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            \Log::error('Export error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export payment schedule projection (collections forecast)
     */
    public function exportPaymentScheduleProjection(Request $request)
    {
        try {
            $validated = $request->validate([
                'months_ahead' => 'nullable|integer|min:1|max:24',
            ]);

            $monthsAhead = $validated['months_ahead'] ?? 12;

            // Get the payment schedule projection data
            $exportData = $this->paymentScheduleProjectionService->getPaymentScheduleProjection($monthsAhead);

            // Use the ExportService
            $exportService = app(\App\Services\ExportService::class);
            
            $fileName = 'cronograma_cobros_proyectado_' . date('YmdHis') . '.xlsx';
            $filePath = $exportService->exportToExcel($exportData, $fileName);

            // Get the full file path - filePath already includes 'exports/'
            $fullPath = storage_path('app/' . $filePath);
            
            \Log::info('Payment schedule projection export - Looking for file at: ' . $fullPath);
            
            if (!file_exists($fullPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo no encontrado',
                    'debug' => [
                        'filePath' => $filePath,
                        'fullPath' => $fullPath,
                        'fileName' => $fileName
                    ]
                ], 404);
            }

            // Return file download response
            return response()->download($fullPath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Cache-Control' => 'no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            \Log::error('Payment schedule export error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar cronograma: ' . $e->getMessage()
            ], 500);
        }
    }
}
