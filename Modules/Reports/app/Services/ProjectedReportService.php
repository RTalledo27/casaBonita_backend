<?php

namespace Modules\Reports\app\Services;

use Modules\Reports\Repositories\ProjectionRepository;
use Carbon\Carbon;

class ProjectedReportService
{
    protected $projectionRepository;

    public function __construct(ProjectionRepository $projectionRepository)
    {
        $this->projectionRepository = $projectionRepository;
    }

    /**
     * Get all projections with filters
     */
    public function getAllProjections(array $filters): array
    {
        $year = $filters['year'] ?? date('Y');
        $scenario = $filters['scenario'] ?? 'realistic';
        $period = $filters['period'] ?? 'monthly';

        // Get base revenue projection using existing repository
        $revenueData = $this->projectionRepository->getMonthlyRevenue(12);
        
        $projections = [];

        // Revenue Projection
        $projections[] = [
            'id' => 1,
            'name' => 'Proyección de Ingresos ' . $year,
            'description' => 'Proyección mensual de ingresos basada en tendencia histórica',
            'type' => 'revenue',
            'period' => [
                'type' => $period,
                'year' => $year
            ],
            'projectedValue' => $this->calculateProjectedRevenue($revenueData, $scenario),
            'variation' => $this->calculateVariation($revenueData),
            'confidence' => $this->calculateConfidence($revenueData),
            'scenario' => $scenario,
            'created_at' => Carbon::now()->toISOString()
        ];

        // Sales Projection
        $projections[] = [
            'id' => 2,
            'name' => 'Proyección de Ventas ' . $year,
            'description' => 'Proyección de número de ventas esperadas',
            'type' => 'sales',
            'period' => [
                'type' => $period,
                'year' => $year
            ],
            'projectedValue' => $this->calculateProjectedSales($revenueData, $scenario),
            'variation' => 8.7,
            'confidence' => 85,
            'scenario' => $scenario,
            'created_at' => Carbon::now()->toISOString()
        ];

        // Cash Flow Projection
        $projections[] = [
            'id' => 3,
            'name' => 'Proyección de Flujo de Caja ' . $year,
            'description' => 'Proyección de flujo de caja neto',
            'type' => 'cashflow',
            'period' => [
                'type' => $period,
                'year' => $year
            ],
            'projectedValue' => $this->calculateProjectedCashFlow($revenueData, $scenario),
            'variation' => 12.3,
            'confidence' => 78,
            'scenario' => $scenario,
            'created_at' => Carbon::now()->toISOString()
        ];

        // ROI Projection
        $projections[] = [
            'id' => 4,
            'name' => 'ROI Proyectado ' . $year,
            'description' => 'Retorno de inversión esperado',
            'type' => 'financial',
            'period' => [
                'type' => 'yearly',
                'year' => $year
            ],
            'projectedValue' => 18.5,
            'variation' => 2.1,
            'confidence' => 82,
            'scenario' => $scenario,
            'created_at' => Carbon::now()->toISOString()
        ];

        // Filter by type if specified
        if (!empty($filters['projection_type'])) {
            $projections = array_filter($projections, function($p) use ($filters) {
                return $p['type'] === $filters['projection_type'];
            });
        }

        return array_values($projections);
    }

    /**
     * Get key metrics
     */
    public function getKeyMetrics(int $year, string $scenario): array
    {
        $revenueData = $this->projectionRepository->getMonthlyRevenue(12);
        
        return [
            'projected_revenue' => $this->calculateProjectedRevenue($revenueData, $scenario),
            'projected_sales' => $this->calculateProjectedSales($revenueData, $scenario),
            'projected_cash_flow' => $this->calculateProjectedCashFlow($revenueData, $scenario),
            'projected_roi' => $this->calculateProjectedROI($revenueData, $scenario),
            'revenue_variation' => $this->calculateVariation($revenueData),
            'sales_variation' => 8.7,
            'cashflow_variation' => 12.3,
            'roi_variation' => 2.1,
            'scenario' => $scenario,
            'year' => $year
        ];
    }

    /**
     * Get revenue projection chart data with three scenarios
     */
    public function getRevenueProjectionChart(int $year, int $monthsAhead): array
    {
        $revenueData = $this->projectionRepository->getMonthlyRevenue(12);
        $projections = $this->projectionRepository->projectFutureMonths($revenueData, $monthsAhead);

        $monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $labels = [];
        $optimisticData = [];
        $realisticData = [];
        $pessimisticData = [];

        foreach ($projections as $projection) {
            $monthIndex = $projection['month'] - 1;
            $labels[] = $monthNames[$monthIndex];
            
            $baseRevenue = $projection['projected_revenue'];
            
            // Apply scenario multipliers
            $optimisticData[] = round($baseRevenue * 1.15, 2); // +15% optimistic
            $realisticData[] = round($baseRevenue, 2);
            $pessimisticData[] = round($baseRevenue * 0.85, 2); // -15% pessimistic
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Optimista',
                    'data' => $optimisticData,
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
                [
                    'label' => 'Realista',
                    'data' => $realisticData,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
                [
                    'label' => 'Pesimista',
                    'data' => $pessimisticData,
                    'borderColor' => '#EF4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                ]
            ]
        ];
    }

    /**
     * Get sales projection chart (monthly comparison with actual vs projected)
     */
    public function getSalesProjectionChart(int $year): array
    {
        // Get historical data
        $historicalData = $this->projectionRepository->getMonthlyRevenue(12);
        
        // Get future projections
        $projections = $this->projectionRepository->projectFutureMonths($historicalData, 6);

        $monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        
        // Extract actual data labels and values
        $actualLabels = [];
        $actualRevenues = [];
        foreach ($historicalData as $data) {
            $monthIndex = $data['month'] - 1;
            $actualLabels[] = $monthNames[$monthIndex];
            $actualRevenues[] = round($data['total_revenue'], 2);
        }
        
        // Extract projected data labels and values
        $projectedLabels = [];
        $projectedRevenues = [];
        foreach ($projections as $projection) {
            $monthIndex = $projection['month'] - 1;
            $projectedLabels[] = $monthNames[$monthIndex];
            $projectedRevenues[] = round($projection['projected_revenue'], 2);
        }
        
        // Combine labels (actual + projected)
        $allLabels = array_merge($actualLabels, $projectedLabels);
        
        // Create datasets with nulls for separation
        $actualData = array_merge($actualRevenues, array_fill(0, count($projectedLabels), null));
        $projectedData = array_merge(array_fill(0, count($actualLabels), null), $projectedRevenues);

        return [
            'labels' => $allLabels,
            'datasets' => [
                [
                    'label' => 'Real',
                    'data' => $actualData,
                    'backgroundColor' => '#6B7280',
                    'borderColor' => '#6B7280',
                    'borderWidth' => 2
                ],
                [
                    'label' => 'Proyectado',
                    'data' => $projectedData,
                    'backgroundColor' => '#3B82F6',
                    'borderColor' => '#3B82F6',
                    'borderWidth' => 2,
                    'borderDash' => [5, 5]
                ]
            ]
        ];
    }

    /**
     * Get cash flow chart data
     */
    public function getCashFlowChart(int $year, int $monthsAhead): array
    {
        $revenueData = $this->projectionRepository->getMonthlyRevenue(12);
        $projections = $this->projectionRepository->projectFutureMonths($revenueData, $monthsAhead);

        $monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $labels = [];
        $incomesData = [];
        $expensesData = [];
        $netFlowData = [];

        foreach ($projections as $projection) {
            $monthIndex = $projection['month'] - 1;
            $labels[] = $monthNames[$monthIndex];
            
            $income = $projection['projected_revenue'];
            $expenses = $income * 0.70; // Assume 70% expenses
            $netFlow = $income - $expenses;
            
            $incomesData[] = round($income, 2);
            $expensesData[] = round($expenses, 2);
            $netFlowData[] = round($netFlow, 2);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Ingresos',
                    'data' => $incomesData,
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
                [
                    'label' => 'Egresos',
                    'data' => $expensesData,
                    'borderColor' => '#EF4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                ],
                [
                    'label' => 'Flujo Neto',
                    'data' => $netFlowData,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true
                ]
            ]
        ];
    }

    /**
     * Get projection detail by ID
     */
    public function getProjectionDetail(int $id): ?array
    {
        $allProjections = $this->getAllProjections([]);
        
        foreach ($allProjections as $projection) {
            if ($projection['id'] === $id) {
                return $projection;
            }
        }
        
        return null;
    }

    // Helper methods for calculations

    private function calculateProjectedRevenue(array $revenueData, string $scenario): float
    {
        if (empty($revenueData)) {
            return 0;
        }

        $totalRevenue = array_sum(array_column($revenueData, 'total_revenue'));
        $avgMonthlyRevenue = $totalRevenue / count($revenueData);
        $annualProjection = $avgMonthlyRevenue * 12;

        // Apply scenario multiplier
        $multipliers = [
            'optimistic' => 1.15,
            'realistic' => 1.0,
            'pessimistic' => 0.85
        ];

        return round($annualProjection * ($multipliers[$scenario] ?? 1.0), 2);
    }

    private function calculateProjectedSales(array $revenueData, string $scenario): int
    {
        if (empty($revenueData)) {
            return 0;
        }

        $totalSales = array_sum(array_column($revenueData, 'sales_count'));
        $avgMonthlySales = $totalSales / count($revenueData);
        $annualProjection = round($avgMonthlySales * 12);

        // Apply scenario multiplier
        $multipliers = [
            'optimistic' => 1.15,
            'realistic' => 1.0,
            'pessimistic' => 0.85
        ];

        return (int) round($annualProjection * ($multipliers[$scenario] ?? 1.0));
    }

    private function calculateProjectedCashFlow(array $revenueData, string $scenario): float
    {
        $projectedRevenue = $this->calculateProjectedRevenue($revenueData, $scenario);
        // Assume 30% net cash flow (70% goes to expenses)
        return round($projectedRevenue * 0.30, 2);
    }

    private function calculateProjectedROI(array $revenueData, string $scenario): float
    {
        // Base ROI calculation (simplified)
        $baseROI = 18.5;
        
        $multipliers = [
            'optimistic' => 1.10,
            'realistic' => 1.0,
            'pessimistic' => 0.90
        ];

        return round($baseROI * ($multipliers[$scenario] ?? 1.0), 1);
    }

    private function calculateVariation(array $revenueData): float
    {
        if (count($revenueData) < 2) {
            return 0;
        }

        $firstMonth = $revenueData[0]['total_revenue'];
        $lastMonth = end($revenueData)['total_revenue'];

        if ($firstMonth == 0) {
            return 0;
        }

        return round((($lastMonth - $firstMonth) / $firstMonth) * 100, 1);
    }

    private function calculateConfidence(array $revenueData): int
    {
        // Calculate confidence based on R² from regression
        $regression = $this->projectionRepository->calculateLinearRegression($revenueData);
        return (int) round($regression['r_squared'] * 100);
    }

    /**
     * Get data formatted for Excel export
     */
    public function getExportData(int $year, string $scenario, int $monthsAhead): array
    {
        // Get historical revenue data for context
        $historicalData = $this->projectionRepository->getMonthlyRevenue(12);
        
        // Get future projections
        $projections = $this->projectionRepository->projectFutureMonths($historicalData, $monthsAhead);

        // Get key metrics
        $metrics = $this->getKeyMetrics($year, $scenario);

        // Prepare data sheets
        $exportData = [];

        // Sheet 1: Resumen Ejecutivo
        $exportData['Resumen Ejecutivo'] = [
            ['Métrica', 'Valor'],
            ['Ingresos Proyectados', 'S/ ' . number_format($metrics['projected_revenue'], 2)],
            ['Ventas Proyectadas', $metrics['projected_sales']],
            ['Flujo de Caja Proyectado', 'S/ ' . number_format($metrics['projected_cash_flow'], 2)],
            ['ROI Proyectado', $metrics['projected_roi'] . '%'],
            ['Escenario', ucfirst($scenario)],
            ['Año', $year],
            ['Fecha de Generación', date('Y-m-d H:i:s')],
        ];

        // Sheet 2: Proyecciones Mensuales Detalladas
        $projectionSheet = [
            ['Mes', 'Año', 'Ingresos Proyectados (S/)', 'Ventas Proyectadas', 'Ticket Promedio (S/)', 'Confianza (%)', 'Método de Cálculo']
        ];
        
        foreach ($projections as $projection) {
            $revenue = $projection['projected_revenue'];
            // Estimar cantidad de ventas basado en ticket promedio histórico
            $avgTicket = 150000; // Ticket promedio aproximado
            $salesCount = round($revenue / $avgTicket);
            
            $projectionSheet[] = [
                $projection['month_label'],
                $projection['year'],
                'S/ ' . number_format($revenue, 2),
                $salesCount,
                'S/ ' . number_format($salesCount > 0 ? $revenue / $salesCount : 0, 2),
                round($projection['confidence'] * 100, 1) . '%',
                $projection['projection_method'] === 'conservative' ? 'Conservador' : 'Regresión Lineal'
            ];
        }
        $exportData['Proyecciones Mensuales'] = $projectionSheet;

        // Sheet 3: Datos Históricos (últimos 12 meses)
        $historicalSheet = [
            ['Mes', 'Año', 'Ingresos Reales (S/)', 'Cantidad de Ventas', 'Ticket Promedio (S/)']
        ];
        foreach ($historicalData as $data) {
            $avgTicket = $data['sales_count'] > 0 ? $data['total_revenue'] / $data['sales_count'] : 0;
            $historicalSheet[] = [
                $data['month_label'],
                $data['year'],
                'S/ ' . number_format($data['total_revenue'], 2),
                $data['sales_count'],
                'S/ ' . number_format($avgTicket, 2)
            ];
        }
        $exportData['Histórico (Últimos 12 meses)'] = $historicalSheet;

        // Sheet 4: Análisis de Escenarios
        $scenarioSheet = [
            ['Mes/Año', 'Escenario Optimista (+15%) S/', 'Escenario Realista S/', 'Escenario Pesimista (-15%) S/']
        ];
        foreach ($projections as $projection) {
            $base = $projection['projected_revenue'];
            $scenarioSheet[] = [
                $projection['month_label'] . ' ' . $projection['year'],
                'S/ ' . number_format($base * 1.15, 2),
                'S/ ' . number_format($base, 2),
                'S/ ' . number_format($base * 0.85, 2)
            ];
        }
        $exportData['Escenarios de Ingresos'] = $scenarioSheet;

        // Sheet 5: Proyección de Flujo de Caja
        $cashFlowSheet = [
            ['Mes/Año', 'Ingresos Proyectados S/', 'Egresos Estimados (70%) S/', 'Flujo Neto (30%) S/', 'Flujo Acumulado S/']
        ];
        
        $accumulatedFlow = 0;
        foreach ($projections as $projection) {
            $income = $projection['projected_revenue'];
            $expenses = $income * 0.70; // Estimación de costos operativos
            $netFlow = $income - $expenses;
            $accumulatedFlow += $netFlow;
            
            $cashFlowSheet[] = [
                $projection['month_label'] . ' ' . $projection['year'],
                'S/ ' . number_format($income, 2),
                'S/ ' . number_format($expenses, 2),
                'S/ ' . number_format($netFlow, 2),
                'S/ ' . number_format($accumulatedFlow, 2)
            ];
        }
        $exportData['Flujo de Caja Proyectado'] = $cashFlowSheet;

        return $exportData;
    }
}
