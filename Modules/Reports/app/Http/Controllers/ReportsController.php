<?php

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Reports\Services\ReportsService;
use Modules\Reports\Services\ExportService;

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
     * Get available report types
     */
    public function getReportTypes(): JsonResponse
    {
        $types = [
            'sales' => 'Reportes de Ventas',
            'payment_schedules' => 'Cronogramas de Pago',
            'projections' => 'Proyecciones Financieras',
            'collections' => 'Reportes de Cobranza',
            'inventory' => 'Reportes de Inventario'
        ];

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * Export report to specified format
     */
    public function export(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:sales,payment_schedules,projections,collections,inventory,projected_statistics,payment_schedule_projection',
            'format' => 'required|string|in:pdf,excel,csv',
            'filters' => 'nullable|array',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'year' => 'nullable|integer',
            'scenario' => 'nullable|string',
            'months_ahead' => 'nullable|integer'
        ]);

        try {
            $type = $request->input('type');
            $format = $request->input('format');
            
            // Handle different report types
            $exportData = [];
            $fileName = '';
            
            switch ($type) {
                case 'sales':
                    // Reporte completo de ventas
                    $salesReportService = app(\Modules\Reports\app\Services\SalesReportService::class);
                    $exportData = $salesReportService->getSalesReportData($request->input('filters', []));
                    $fileName = 'reporte_ventas_' . date('YmdHis') . '.xlsx';
                    break;
                    
                case 'projected_statistics':
                    // Proyecciones estadísticas (regresión lineal)
                    $projectedService = app(\Modules\Reports\app\Services\ProjectedReportService::class);
                    $year = $request->input('year', date('Y'));
                    $scenario = $request->input('scenario', 'realistic');
                    $monthsAhead = $request->input('months_ahead', 12);
                    
                    $exportData = $projectedService->getExportData($year, $scenario, $monthsAhead);
                    $fileName = 'proyecciones_estadisticas_' . date('YmdHis') . '.xlsx';
                    break;
                    
                case 'payment_schedule_projection':
                    // Cronograma de cobros proyectado
                    $paymentScheduleService = app(\Modules\Reports\app\Services\PaymentScheduleProjectionService::class);
                    $monthsAhead = $request->input('months_ahead', 12);
                    
                    $exportData = $paymentScheduleService->getPaymentScheduleProjection($monthsAhead);
                    $fileName = 'cronograma_cobros_' . date('YmdHis') . '.xlsx';
                    break;
                    
                default:
                    // Reportes tradicionales (ventas, etc.)
                    $userId = auth()->check() ? auth()->user()->user_id : null;
                    
                    $result = $this->exportService->generateReport(
                        $type,
                        $format,
                        $request->input('filters', []),
                        $request->input('date_from'),
                        $request->input('date_to'),
                        $userId
                    );

                    \Log::info('Export result:', $result);

                    // Normalize the file path for Windows
                    $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $result['file_path']);
                    
                    // Get the file path from storage
                    $filePath = storage_path('app' . DIRECTORY_SEPARATOR . $normalizedPath);
                    
                    \Log::info('Looking for file at: ' . $filePath);
                    \Log::info('File exists: ' . (file_exists($filePath) ? 'YES' : 'NO'));
                    
                    if (!file_exists($filePath)) {
                        // Try alternative path
                        $alternativePath = storage_path($result['file_path']);
                        \Log::info('Trying alternative path: ' . $alternativePath);
                        
                        if (file_exists($alternativePath)) {
                            $filePath = $alternativePath;
                        } else {
                            return response()->json([
                                'success' => false,
                                'message' => 'Archivo no encontrado',
                                'debug' => [
                                    'result' => $result,
                                    'tried_paths' => [
                                        storage_path('app/' . $result['file_path']),
                                        $alternativePath
                                    ]
                                ]
                            ], 404);
                        }
                    }

                    // Determine content type based on format
                    $contentType = match($format) {
                        'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'csv' => 'text/csv',
                        'pdf' => 'application/pdf',
                        default => 'application/octet-stream'
                    };

                    // Generate filename
                    $filename = basename($result['file_path']);

                    // Return file download response
                    return response()->download($filePath, $filename, [
                        'Content-Type' => $contentType,
                        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                        'Cache-Control' => 'no-cache, must-revalidate',
                        'Pragma' => 'no-cache',
                        'Expires' => '0'
                    ])->deleteFileAfterSend(true);
            }
            
            // For projected reports, generate Excel file
            if (!empty($exportData)) {
                $exportService = app(\App\Services\ExportService::class);
                $relativeFilePath = $exportService->exportToExcel($exportData, $fileName);
                
                $filePath = storage_path('app/' . $relativeFilePath);
                
                if (!file_exists($filePath)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Archivo no encontrado',
                        'debug' => [
                            'filePath' => $relativeFilePath,
                            'fullPath' => $filePath
                        ]
                    ], 404);
                }
                
                // Return file download response for projected reports
                return response()->download($filePath, $fileName, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                    'Cache-Control' => 'no-cache, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0'
                ])->deleteFileAfterSend(true);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get report generation status
     */
    public function getReportStatus($reportId): JsonResponse
    {
        try {
            $status = $this->reportsService->getReportStatus($reportId);
            
            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el estado del reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download generated report
     */
    public function download($reportId)
    {
        try {
            return $this->exportService->downloadReport($reportId);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar el reporte: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get user's generated reports history
     */
    public function getReportsHistory(Request $request): JsonResponse
    {
        try {
            $reports = $this->reportsService->getUserReports(
                auth()->user()->user_id,
                $request->input('page', 1),
                $request->input('per_page', 10)
            );

            return response()->json([
                'success' => true,
                'data' => $reports
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial de reportes: ' . $e->getMessage()
            ], 500);
        }
    }
}
