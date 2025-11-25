<?php

namespace App\Http\Controllers;

use App\Services\ReportsService;
use App\Services\ExportService;
use App\Services\ProjectionsService;
use App\Services\Reports\ExcelReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    protected $reportsService;
    protected $exportService;
    protected $projectionsService;
    protected $excelReportService;

    public function __construct(
        ReportsService $reportsService, 
        ExportService $exportService,
        ProjectionsService $projectionsService,
        ExcelReportService $excelReportService
    ) {
        $this->reportsService = $reportsService;
        $this->exportService = $exportService;
        $this->projectionsService = $projectionsService;
        $this->excelReportService = $excelReportService;
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

    /**
     * Get sales consolidated data
     */
    public function getSalesConsolidated(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['month', 'year', 'advisor_id', 'team_id', 'office_id']);
            
            // Get all sales with consolidated data
            $salesData = DB::table('sales as s')
                ->join('contracts as c', 's.contract_id', '=', 'c.contract_id')
                ->join('users as u', 's.advisor_id', '=', 'u.id')
                ->leftJoin('teams as t', 's.team_id', '=', 't.team_id')
                ->select(
                    's.*',
                    'c.contract_number',
                    'c.client_name',
                    'u.first_name as advisor_first_name',
                    'u.last_name as advisor_last_name',
                    't.team_name'
                );

            // Apply filters
            if (!empty($filters['year'])) {
                $salesData->whereYear('s.sale_date', $filters['year']);
            }
            if (!empty($filters['month'])) {
                $salesData->whereMonth('s.sale_date', $filters['month']);
            }
            if (!empty($filters['advisor_id'])) {
                $salesData->where('s.advisor_id', $filters['advisor_id']);
            }
            if (!empty($filters['team_id'])) {
                $salesData->where('s.team_id', $filters['team_id']);
            }
            if (!empty($filters['office_id'])) {
                $salesData->where('s.office_id', $filters['office_id']);
            }

            $results = $salesData->orderBy('s.sale_date', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener ventas consolidadas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get monthly projections
     */
    public function getProjectionsMonthly(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', date('Y'));
            $officeId = $request->get('office_id');
            $type = $request->get('type', 'revenue'); // revenue, sales, collections

            $projections = $this->projectionsService->getProjections(
                $type,
                12,
                "{$year}-01-01",
                $officeId
            );

            return response()->json([
                'success' => true,
                'data' => $projections
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proyecciones mensuales',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Monthly Income Report (Image 1 format)
     */
    public function exportMonthlyIncome(Request $request)
    {
        try {
            $year = $request->get('year', date('Y'));
            $filters = $request->only(['advisor_id', 'office_id', 'team_id']);

            $result = $this->excelReportService->generateMonthlyIncomeReport($year, $filters);

            return response()->download($result['filepath'], $result['filename'], [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar reporte de ingresos mensuales',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Detailed Sales Report (Image 2 format)
     */
    public function exportDetailedSales(Request $request)
    {
        try {
            $filters = $request->only(['month', 'year', 'advisor_id', 'team_id', 'office_id']);

            $result = $this->excelReportService->generateDetailedSalesReport($filters);

            return response()->download($result['filepath'], $result['filename'], [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar reporte detallado de ventas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Client Details Report (Image 3 format)
     */
    public function exportClientDetails(Request $request)
    {
        try {
            $filters = $request->only(['month', 'year', 'status', 'advisor_id']);

            $result = $this->excelReportService->generateClientDetailsReport($filters);

            return response()->download($result['filepath'], $result['filename'], [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar detalles de clientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}