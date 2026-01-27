<?php

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Reports\Services\SalesReportsService;

class SalesReportsController extends Controller
{
    protected $salesReportsService;

    public function __construct(SalesReportsService $salesReportsService)
    {
        $this->salesReportsService = $salesReportsService;
    }

    /**
     * Get all sales with detailed information
     */
    public function getAllSales(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'employee_id' => 'nullable|integer',
            'project_id' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1|max:500',
            'offset' => 'nullable|integer|min:0'
        ]);

        try {
            \Log::info('Get all sales request:', $request->all());
            
            $sales = $this->salesReportsService->getAllSales(
                $request->input('date_from'),
                $request->input('date_to'),
                $request->input('employee_id'),
                $request->input('project_id'),
                $request->input('limit', 100),
                $request->input('offset', 0)
            );

            return response()->json([
                'success' => true,
                'data' => $sales,
                'count' => $sales->count()
            ]);
        } catch (\Exception $e) {
            \Log::error('Get all sales error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener todas las ventas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales dashboard data
     */
    public function getDashboard(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'employee_id' => 'nullable|integer',
            'project_id' => 'nullable|integer'
        ]);

        try {
            \Log::info('Dashboard request received:', $request->all());
            
            $data = $this->salesReportsService->getDashboardData(
                $request->input('date_from'),
                $request->input('date_to'),
                $request->input('employee_id'),
                $request->input('project_id')
            );

            \Log::info('Dashboard response data:', $data);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            \Log::error('Dashboard error: ' . $e->getMessage());
            \Log::error('Dashboard error trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos del dashboard: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales by period
     */
    public function getSalesByPeriod(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|string|in:daily,weekly,monthly,quarterly,yearly',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'employee_id' => 'nullable|integer'
        ]);

        try {
            $data = $this->salesReportsService->getSalesByPeriod(
                $request->input('period'),
                $request->input('date_from'),
                $request->input('date_to'),
                $request->input('employee_id')
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener ventas por período: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export sales report to Excel
     */
    public function exportToExcel(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'employee_id' => 'nullable|integer',
            'project_id' => 'nullable|integer',
            'report_type' => 'nullable|string|in:monthly_income,detailed_sales,client_details,basic'
        ]);

        try {
            $reportType = $request->input('report_type', 'basic');
            
            // Get the dashboard data
            $data = $this->salesReportsService->getDashboardData(
                $request->input('date_from'),
                $request->input('date_to'),
                $request->input('employee_id'),
                $request->input('project_id')
            );

            // Use the ExportService to create Excel file
            $exportService = app(\App\Services\ExportService::class);
            
            // Generate export data based on report type
            $exportData = $this->generateExportData($reportType, $data, $request);
            
            $fileName = $this->getFileName($reportType) . '.xlsx';
            $filePath = $exportService->exportToExcel($exportData, $fileName);

            return response()->download(storage_path('app/' . $filePath));

        } catch (\Exception $e) {
            \Log::error('Export error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Monthly Income (Image 1)
     */
    public function exportMonthlyIncome(Request $request)
    {
        $request->validate([
            'year' => 'nullable|integer',
            'advisor_id' => 'nullable|integer',
            'office_id' => 'nullable|integer',
        ]);

        try {
            $year = $request->input('year', date('Y'));
            $filters = $request->only(['advisor_id', 'office_id']);

            $excelService = app(\App\Services\Reports\ExcelReportService::class);
            $result = $excelService->generateMonthlyIncomeReport((int)$year, $filters);

            return response()->download($result['filepath']);
        } catch (\Exception $e) {
            \Log::error('Export monthly income error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de ingresos mensuales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Detailed Sales (Image 2)
     */
    public function exportDetailedSales(Request $request)
    {
        $request->validate([
            'year' => 'nullable|integer',
            'month' => 'nullable|integer',
            'advisor_id' => 'nullable|integer',
            'office_id' => 'nullable|integer',
        ]);

        try {
            $filters = $request->only(['year', 'month', 'advisor_id', 'office_id', 'startDate', 'endDate']);

            $excelService = app(\App\Services\Reports\ExcelReportService::class);
            $result = $excelService->generateDetailedSalesReport($filters);

            return response()->download(
                $result['filepath'],
                $result['filename'],
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"',
                    'Cache-Control' => 'no-cache, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0'
                ]
            )->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            \Log::error('Export detailed sales error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de ventas detalladas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Client Details (Image 3)
     */
    public function exportClientDetails(Request $request)
    {
        $request->validate([
            'year' => 'nullable|integer',
            'month' => 'nullable|integer',
            'advisor_id' => 'nullable|integer',
        ]);

        try {
            $filters = $request->only(['year', 'month', 'advisor_id']);

            $excelService = app(\App\Services\Reports\ExcelReportService::class);
            $result = $excelService->generateClientDetailsReport($filters);

            return response()->download($result['filepath']);
        } catch (\Exception $e) {
            \Log::error('Export client details error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de detalles de clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate export data based on report type
     */
    private function generateExportData(string $reportType, array $data, Request $request): array
    {
        switch ($reportType) {
            case 'monthly_income':
                return $this->generateMonthlyIncomeData($data, $request);
            
            case 'detailed_sales':
                return $this->generateDetailedSalesData($data, $request);
            
            case 'client_details':
                return $this->generateClientDetailsData($data, $request);
            
            default:
                return $this->generateBasicReportData($data);
        }
    }

    /**
     * Generate Monthly Income Report Data
     */
    private function generateMonthlyIncomeData(array $data, Request $request): array
    {
        $exportData = [
            'Ingresos Mensuales' => [
                ['Mes', 'Ingresos', 'Número de Ventas', 'Promedio por Venta'],
            ]
        ];

        // Add monthly data if available
        if (!empty($data['trends'])) {
            foreach ($data['trends'] as $trend) {
                $exportData['Ingresos Mensuales'][] = [
                    $trend->period ?? 'N/A',
                    '$' . number_format($trend->total_revenue ?? 0, 2),
                    $trend->sales_count ?? 0,
                    '$' . number_format($trend->average_sale ?? 0, 2)
                ];
            }
        }

        // Add summary
        $summary = $data['summary'];
        $exportData['Resumen'] = [
            ['Métrica', 'Valor'],
            ['Total Anual', '$' . number_format($summary->total_revenue ?? 0, 2)],
            ['Total Ventas', $summary->total_sales ?? 0],
            ['Promedio Mensual', '$' . number_format(($summary->total_revenue ?? 0) / 12, 2)],
        ];

        return $exportData;
    }

    /**
     * Generate Detailed Sales Report Data
     */
    private function generateDetailedSalesData(array $data, Request $request): array
    {
        // Get all sales with full details
        $sales = $this->salesReportsService->getAllSales(
            $request->input('date_from'),
            $request->input('date_to'),
            $request->input('employee_id'),
            $request->input('project_id'),
            500, // limit
            0    // offset
        );

        $exportData = [
            'Ventas Detalladas' => [
                ['Fecha', 'Asesor', 'Cliente', 'Lote', 'Monto Total', 'Inicial', 'Financiado', 'Cuotas', 'Estado'],
            ]
        ];

        foreach ($sales as $sale) {
            $exportData['Ventas Detalladas'][] = [
                $sale->sign_date ?? 'N/A',
                $sale->advisor_name ?? 'N/A',
                $sale->client_name ?? 'N/A',
                $sale->lot_number ?? 'N/A',
                '$' . number_format($sale->total_price ?? 0, 2),
                '$' . number_format($sale->down_payment ?? 0, 2),
                '$' . number_format($sale->financing_amount ?? 0, 2),
                $sale->term_months ?? 0,
                $sale->status ?? 'N/A'
            ];
        }

        return $exportData;
    }

    /**
     * Generate Client Details Report Data
     */
    private function generateClientDetailsData(array $data, Request $request): array
    {
        // Get all sales with client details
        $sales = $this->salesReportsService->getAllSales(
            $request->input('date_from'),
            $request->input('date_to'),
            $request->input('employee_id'),
            $request->input('project_id'),
            500,
            0
        );

        $exportData = [
            'Detalles de Clientes' => [
                ['Cliente', 'Teléfono', 'Email', 'Lote', 'Fecha Venta', 'Monto', 'Asesor', 'Estado'],
            ]
        ];

        foreach ($sales as $sale) {
            $exportData['Detalles de Clientes'][] = [
                $sale->client_name ?? 'N/A',
                $sale->client_phone ?? 'N/A',
                $sale->client_email ?? 'N/A',
                $sale->lot_number ?? 'N/A',
                $sale->sign_date ?? 'N/A',
                '$' . number_format($sale->total_price ?? 0, 2),
                $sale->advisor_name ?? 'N/A',
                $sale->status ?? 'N/A'
            ];
        }

        return $exportData;
    }

    /**
     * Generate Basic Report Data (default)
     */
    private function generateBasicReportData(array $data): array
    {
        $exportData = [
            'Resumen de Ventas' => [
                ['Métrica', 'Valor'],
                ['Total de Ventas', $data['summary']->total_sales ?? 0],
                ['Ingresos Totales', '$' . number_format($data['summary']->total_revenue ?? 0, 2)],
                ['Venta Promedio', '$' . number_format($data['summary']->average_sale ?? 0, 2)],
                // ['Crecimiento de Ventas', ($data['summary']->sales_growth ?? 0) . '%'] // sales_growth might not be in the object
            ]
        ];

        // Add trends if available
        if (!empty($data['trends'])) {
            $trendsData = [['Período', 'Ventas', 'Ingresos']];
            foreach ($data['trends'] as $trend) {
                $trendsData[] = [
                    $trend->period ?? '',
                    $trend->sales_count ?? 0,
                    '$' . number_format($trend->total_revenue ?? 0, 2)
                ];
            }
            $exportData['Tendencias'] = $trendsData;
        }

        // Add top performers if available
        if (!empty($data['top_performers'])) {
            $performersData = [['Empleado', 'Ventas', 'Ingresos']];
            foreach ($data['top_performers'] as $performer) {
                $performersData[] = [
                    $performer->employee_name ?? '',
                    $performer->sales_count ?? 0,
                    '$' . number_format($performer->total_revenue ?? 0, 2)
                ];
            }
            $exportData['Mejores Vendedores'] = $performersData;
        }

        return $exportData;
    }

    /**
     * Get filename based on report type
     */
    private function getFileName(string $reportType): string
    {
        $date = date('Y-m-d_H-i-s');
        
        switch ($reportType) {
            case 'monthly_income':
                return 'ingresos_mensuales_' . $date;
            case 'detailed_sales':
                return 'ventas_detalladas_' . $date;
            case 'client_details':
                return 'detalles_clientes_' . $date;
            default:
                return 'reporte_ventas_' . $date;
        }
    }
}
