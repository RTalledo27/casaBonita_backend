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
                'success' => false,
                'message' => 'Error al obtener ventas por período: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales performance by employee
     */
    public function getSalesPerformance(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'department' => 'nullable|string'
        ]);

        try {
            $data = $this->salesReportsService->getSalesPerformance(
                $request->input('date_from'),
                $request->input('date_to'),
                $request->input('department')
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener rendimiento de ventas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get conversion funnel data
     */
    public function getConversionFunnel(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'employee_id' => 'nullable|integer'
        ]);

        try {
            $data = $this->salesReportsService->getConversionFunnel(
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
                'success' => false,
                'message' => 'Error al obtener embudo de conversión: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top selling products/lots
     */
    public function getTopProducts(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        try {
            $data = $this->salesReportsService->getTopProducts(
                $request->input('date_from'),
                $request->input('date_to'),
                $request->input('limit', 10)
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener productos más vendidos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export sales report to Excel
     */
    public function exportToExcel(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'employee_id' => 'nullable|integer',
            'project_id' => 'nullable|integer'
        ]);

        try {
            // Get the dashboard data
            $data = $this->salesReportsService->getDashboardData(
                $request->input('date_from'),
                $request->input('date_to'),
                $request->input('employee_id'),
                $request->input('project_id')
            );

            // Use the ExportService to create Excel file
            $exportService = app(\App\Services\ExportService::class);
            
            // Prepare data for export
            $exportData = [
                'Resumen de Ventas' => [
                    ['Métrica', 'Valor'],
                    ['Total de Ventas', $data['summary']['total_sales'] ?? 0],
                    ['Ingresos Totales', '$' . number_format($data['summary']['total_revenue'] ?? 0, 2)],
                    ['Venta Promedio', '$' . number_format($data['summary']['average_sale'] ?? 0, 2)],
                    ['Crecimiento de Ventas', ($data['summary']['sales_growth'] ?? 0) . '%']
                ]
            ];

            // Add trends if available
            if (!empty($data['trends'])) {
                $trendsData = [['Período', 'Ventas', 'Ingresos']];
                foreach ($data['trends'] as $trend) {
                    $trendsData[] = [
                        $trend['period'] ?? '',
                        $trend['sales'] ?? 0,
                        '$' . number_format($trend['revenue'] ?? 0, 2)
                    ];
                }
                $exportData['Tendencias'] = $trendsData;
            }

            // Add top performers if available
            if (!empty($data['top_performers'])) {
                $performersData = [['Empleado', 'Ventas', 'Ingresos']];
                foreach ($data['top_performers'] as $performer) {
                    $performersData[] = [
                        $performer['name'] ?? '',
                        $performer['sales'] ?? 0,
                        '$' . number_format($performer['revenue'] ?? 0, 2)
                    ];
                }
                $exportData['Mejores Vendedores'] = $performersData;
            }

            $fileName = 'reporte_ventas_' . date('Y-m-d_H-i-s') . '.xlsx';
            $filePath = $exportService->exportToExcel($exportData, $fileName);

            return response()->json([
                'success' => true,
                'message' => 'Reporte exportado exitosamente',
                'file_path' => $filePath,
                'download_url' => url('storage/exports/' . $fileName)
            ]);

        } catch (\Exception $e) {
            \Log::error('Export error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar reporte: ' . $e->getMessage()
            ], 500);
        }
    }
}
