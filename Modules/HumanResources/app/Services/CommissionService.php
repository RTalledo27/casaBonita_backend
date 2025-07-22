<?php

namespace Modules\HumanResources\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Repositories\CommissionRepository;
use Modules\HumanResources\Repositories\EmployeeRepository;
use Modules\Sales\Models\Contract;

class CommissionService
{
    public function __construct(
        protected CommissionRepository $commissionRepo,
        protected EmployeeRepository $employeeRepo
    ) {}

    public function processCommissionsForPeriod(int $month, int $year): array
    {
        $commissions = [];
        
        DB::transaction(function () use ($month, $year, &$commissions) {
            // Obtener contratos firmados en el período
            $contracts = Contract::with('advisor')
                               ->whereMonth('sign_date', $month)
                               ->whereYear('sign_date', $year)
                               ->where('status', 'vigente')
                               ->whereNotNull('advisor_id')
                               ->get();

            foreach ($contracts as $contract) {
                // Verificar si ya existe comisión para este contrato
                $existingCommission = Commission::where('contract_id', $contract->contract_id)
                                               ->where('period_month', $month)
                                               ->where('period_year', $year)
                                               ->first();

                if (!$existingCommission && $contract->advisor && $contract->financing_amount > 0) {
                    $totalCommissionAmount = $this->calculateCommission($contract);
                    
                    // Solo procesar si hay comisión a pagar
                    if ($totalCommissionAmount > 0) {
                        $salesCount = $this->getAdvisorFinancedSalesCount($contract->advisor_id, $contract->sign_date);
                        $commissionRate = $this->getCommissionRate($salesCount, $contract->term_months);
                        
                        // Crear comisiones divididas según el número de ventas
                        $commissionParts = $this->createSplitCommissions(
                            $contract,
                            $totalCommissionAmount,
                            $commissionRate,
                            $salesCount,
                            $month,
                            $year
                        );
                        
                        $commissions = array_merge($commissions, $commissionParts);
                    }
                }
            }
        });

        return $commissions;
    }

    public function calculateCommission(Contract $contract): float
    {
        if (!$contract->advisor) {
            return 0;
        }

        // Solo calcular comisiones para ventas financiadas
        if (!$contract->financing_amount || $contract->financing_amount <= 0) {
            return 0;
        }

        $baseAmount = $contract->financing_amount;
        $termMonths = $contract->term_months;
        
        // Contar ventas financiadas del asesor en el mismo período
        $salesCount = $this->getAdvisorFinancedSalesCount($contract->advisor_id, $contract->sign_date);
        
        // Determinar el porcentaje según la tabla de comisiones
        $commissionRate = $this->getCommissionRate($salesCount, $termMonths);
        
        return $baseAmount * ($commissionRate / 100);
    }

    /**
     * Obtiene el número de ventas financiadas del asesor en el período
     */
    private function getAdvisorFinancedSalesCount(int $advisorId, $signDate): int
    {
        $month = date('n', strtotime($signDate));
        $year = date('Y', strtotime($signDate));
        
        return Contract::where('advisor_id', $advisorId)
            ->whereMonth('sign_date', $month)
            ->whereYear('sign_date', $year)
            ->where('status', 'vigente')
            ->whereNotNull('financing_amount')
            ->where('financing_amount', '>', 0)
            ->count();
    }

    /**
      * Determina el porcentaje de comisión según la tabla de rangos
      */
    private function getCommissionRate(int $salesCount, int $termMonths): float
    {
        // Determinar si es plazo corto (12/24/36) o largo (48/60)
        $isShortTerm = in_array($termMonths, [12, 24, 36]);
        
        if ($salesCount >= 10) {
            return $isShortTerm ? 4.20 : 3.00;
        } elseif ($salesCount >= 8) {
            return $isShortTerm ? 4.00 : 2.50;
        } elseif ($salesCount >= 6) {
            return $isShortTerm ? 3.00 : 1.50;
        } else {
            return $isShortTerm ? 2.00 : 1.00;
        }
    }

    /**
     * Crea comisiones divididas según el número de ventas
     */
    private function createSplitCommissions(
        Contract $contract,
        float $totalAmount,
        float $commissionRate,
        int $salesCount,
        int $month,
        int $year
    ): array {
        $commissions = [];
        
        // Determinar porcentajes de división según número de ventas
        if ($salesCount > 10) {
            // Más de 10 ventas: 70% primer mes, 30% segundo mes
            $firstPaymentPercentage = 70;
            $secondPaymentPercentage = 30;
        } else {
            // 1-10 ventas: 50% primer mes, 50% segundo mes
            $firstPaymentPercentage = 50;
            $secondPaymentPercentage = 50;
        }
        
        // Crear primera comisión (mes actual)
        $firstAmount = ($totalAmount * $firstPaymentPercentage) / 100;
        $firstCommission = $this->commissionRepo->create([
            'employee_id' => $contract->advisor_id,
            'contract_id' => $contract->contract_id,
            'commission_type' => 'venta_financiada',
            'sale_amount' => $contract->financing_amount,
            'installment_plan' => $contract->term_months,
            'commission_percentage' => $commissionRate,
            'commission_amount' => round($firstAmount, 2),
            'period_month' => $month,
            'period_year' => $year,
            'payment_status' => 'pendiente',
            'payment_type' => $secondPaymentPercentage > 0 ? 'first_payment' : 'full_payment',
            'total_commission_amount' => $totalAmount,
            'sales_count' => $salesCount,
            'notes' => "Comisión por venta financiada - {$salesCount} ventas - Pago 1 de " . ($secondPaymentPercentage > 0 ? '2' : '1')
        ]);
        
        $commissions[] = $firstCommission;
        
        // Crear segunda comisión si es necesario (mes siguiente)
        if ($secondPaymentPercentage > 0) {
            $secondAmount = ($totalAmount * $secondPaymentPercentage) / 100;
            $nextMonth = $month + 1;
            $nextYear = $year;
            
            // Ajustar año si es diciembre
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear++;
            }
            
            $secondCommission = $this->commissionRepo->create([
                'employee_id' => $contract->advisor_id,
                'contract_id' => $contract->contract_id,
                'commission_type' => 'venta_financiada',
                'sale_amount' => $contract->financing_amount,
                'installment_plan' => $contract->term_months,
                'commission_percentage' => $commissionRate,
                'commission_amount' => round($secondAmount, 2),
                'period_month' => $nextMonth,
                'period_year' => $nextYear,
                'payment_status' => 'pendiente',
                'payment_type' => 'second_payment',
                'total_commission_amount' => $totalAmount,
                'sales_count' => $salesCount,
                'notes' => "Comisión por venta financiada - {$salesCount} ventas - Pago 2 de 2"
            ]);
            
            $commissions[] = $secondCommission;
        }
        
        return $commissions;
    }

    public function payCommissions(array $commissionIds): bool
    {
        return DB::transaction(function () use ($commissionIds) {
            $updated = $this->commissionRepo->markMultipleAsPaid($commissionIds);
            return $updated > 0;
        });
    }

    /**
     * Obtiene el detalle de ventas individuales con sus comisiones para un asesor
     */
    public function getAdvisorSalesDetail(int $employeeId, int $month, int $year): array
    {
        // Obtener empleado para verificar elegibilidad
        $employee = $this->employeeRepo->findById($employeeId);
        if (!$employee || !$employee->is_commission_eligible) {
            throw new \Exception('Empleado no encontrado o no es elegible para comisiones');
        }

        // Obtener todos los contratos del asesor en el período
        $contracts = Contract::with(['reservation.lot.manzana', 'reservation.client'])
            ->where('advisor_id', $employeeId)
            ->whereMonth('sign_date', $month)
            ->whereYear('sign_date', $year)
            ->where('financing_amount', '>', 0)
            ->orderBy('sign_date')
            ->get();

        $salesDetail = [];
        $totalCommissions = 0;
        $salesCount = $contracts->count();
        $firstMonthTotal = 0;
        $secondMonthTotal = 0;
        
        // Determinar tipo de división según cantidad de ventas
        $splitType = $salesCount > 10 ? '70/30' : '50/50';
        $firstPercentage = $salesCount > 10 ? 70 : 50;
        $secondPercentage = $salesCount > 10 ? 30 : 50;

        foreach ($contracts as $index => $contract) {
            $saleNumber = $index + 1;
            
            // Calcular comisión para esta venta específica
            $commissionRate = $this->getCommissionRate($saleNumber, $contract->term_months);
            $commissionAmount = $contract->financing_amount * ($commissionRate / 100);
            
            // Calcular división de pagos
            $firstPayment = $commissionAmount * ($firstPercentage / 100);
            $secondPayment = $commissionAmount * ($secondPercentage / 100);
            
            $firstMonthTotal += $firstPayment;
            $secondMonthTotal += $secondPayment;
            
            // Obtener las comisiones reales creadas para este contrato
            $commissions = Commission::where('contract_id', $contract->contract_id)
                ->where('employee_id', $employeeId)
                ->get();

            $saleDetail = [
                'contract_number' => $contract->contract_number,
                'client_name' => $contract->reservation->client->full_name ?? 'N/A',
                'financing_amount' => $contract->financing_amount,
                'term_months' => $contract->term_months,
                'commission_percentage' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'first_payment' => $firstPayment,
                'second_payment' => $secondPayment,
                'payment_split_type' => $splitType,
                'project_name' => $contract->reservation->lot->manzana->name ?? 'N/A',
                'lot_number' => $contract->reservation->lot->num_lot ?? 'N/A',
                'sign_date' => $contract->sign_date->format('Y-m-d'),
                'commissions' => []
            ];

            // Agregar detalles de las comisiones divididas
            foreach ($commissions as $commission) {
                $saleDetail['commissions'][] = [
                    'commission_id' => $commission->commission_id,
                    'payment_type' => $commission->payment_type,
                    'commission_amount' => $commission->commission_amount,
                    'payment_status' => $commission->payment_status,
                    'payment_date' => $commission->payment_date?->format('Y-m-d'),
                    'period_month' => $commission->period_month,
                    'period_year' => $commission->period_year
                ];
            }

            $salesDetail[] = $saleDetail;
            $totalCommissions += $commissionAmount;
        }

        return [
            'success' => true,
            'data' => [
                'summary' => [
                    'total_sales' => $salesCount,
                    'total_commission' => $totalCommissions,
                    'average_percentage' => $salesCount > 0 ? ($totalCommissions / $contracts->sum('financing_amount')) * 100 : 0,
                    'first_month_total' => $firstMonthTotal,
                    'second_month_total' => $secondMonthTotal,
                    'split_type' => $splitType
                ],
                'sales' => $salesDetail,
                'employee' => $employee,
                'period' => [
                    'month' => $month,
                    'year' => $year
                ]
            ],
            'message' => 'Detalle de ventas obtenido exitosamente'
        ];
    }

    public function getTopPerformers(int $month, int $year, int $limit = 10): Collection
    {
        return $this->employeeRepo->getTopPerformers($month, $year, $limit);
    }

    public function getAdvisorDashboard(int $employeeId, int $month, int $year): array
    {
        $employee = $this->employeeRepo->findById($employeeId);
        
        if (!$employee || !$employee->is_commission_eligible) {
            throw new \Exception('Empleado no encontrado o no es elegible para comisiones');
        }

        // Obtener contratos del mes con las relaciones necesarias
        $monthlySales = Contract::with(['reservation.client'])
            ->whereHas('reservation', function ($query) use ($employeeId, $month, $year) {
                 $query->where('advisor_id', $employeeId)
                     ->whereMonth('reservation_date', $month)
                     ->whereYear('reservation_date', $year);
             })
            ->where('status', 'vigente')
            ->get();

        $monthlyCommissions = $employee->calculateMonthlyCommissions($month, $year);
        $monthlyBonuses = $employee->calculateMonthlyBonuses($month, $year);
        
        $topPerformers = $this->getTopPerformers($month, $year);
        $ranking = $topPerformers->search(function ($performer) use ($employeeId) {
            return $performer->employee_id === $employeeId;
        });

        return [
            'employee' => $employee,
            'period' => [
                'month' => $month,
                'year' => $year,
                'label' => $this->getMonthLabel($month) . ' ' . $year
            ],
            'sales_summary' => [
                'count' => $monthlySales->count(),
                'total_amount' => $monthlySales->sum('total_price'),
                'goal' => $employee->individual_goal ?? 0,
                'achievement_percentage' => $employee->individual_goal > 0 
                    ? round(($monthlySales->sum('total_price') / $employee->individual_goal) * 100, 2)
                    : 0
            ],
            'earnings_summary' => [
                'base_salary' => (float)$employee->base_salary,
                'commissions' => (float)$monthlyCommissions,
                'bonuses' => (float)$monthlyBonuses,
                'total_estimated' => $employee->base_salary +
                    $monthlyCommissions +
                    $monthlyBonuses
            ],
            'performance' => [
                'ranking' => $ranking !== false ? $ranking + 1 : null,
                'total_advisors' => $topPerformers->count()
            ],
            'recent_contracts' => $monthlySales->take(5)->map(function ($contract) {
                $clientName = 'Cliente no disponible';
                if ($contract->reservation && $contract->reservation->client) {
                    $client = $contract->reservation->client;
                    $clientName = ($client->first_name ?? '') . ' ' . ($client->last_name ?? '');
                    $clientName = trim($clientName) ?: 'Cliente sin nombre';
                }
                
                return [
                    'contract_number' => $contract->contract_number,
                    'total_price' => $contract->total_price,
                    'sign_date' => $contract->sign_date->format('Y-m-d'),
                    'client_name' => $clientName
                ];
            })
        ];
    }

    public function getAdminDashboard(int $month, int $year): array
    {
        $totalCommissions = $this->commissionRepo->getTotalCommissionsForPeriod($month, $year);
        $commissionsCount = $this->commissionRepo->getAll([
            'period_month' => $month,
            'period_year' => $year
        ])->count();

        $topPerformers = $this->getTopPerformers($month, $year);

        $bonusService = app(\Modules\HumanResources\Services\BonusService::class);
        $bonusSummary = $bonusService->getBonusSummary($month, $year);

        return [
            'period' => [
                'month' => $month,
                'year' => $year,
                'label' => $this->getMonthLabel($month) . ' ' . $year
            ],
            'commissions_summary' => [
                'total_amount' => $totalCommissions,
                'count' => $commissionsCount
            ],
            'bonuses_summary' => $bonusSummary,
            'top_performers' => $topPerformers
        ];
    }


    private function getMonthLabel(int $month): string
    {
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        
        return $months[$month] ?? 'Mes desconocido';
    }

}