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
                               ->where('status', 'signed')
                               ->whereNotNull('advisor_id')
                               ->get();

            foreach ($contracts as $contract) {
                // Verificar si ya existe comisión para este contrato
                $existingCommission = Commission::where('contract_id', $contract->contract_id)
                                               ->where('period_month', $month)
                                               ->where('period_year', $year, $month)
                                               ->where('period_year', $year)
                                               ->first();

                if (!$existingCommission && $contract->advisor) {
                    $commissionAmount = $this->calculateCommission($contract);
                    
                    $commission = $this->commissionRepo->create([
                        'employee_id' => $contract->advisor_id,
                        'contract_id' => $contract->contract_id,
                        'commission_type' => 'sale',
                        'base_amount' => $contract->total_price,
                        'commission_rate' => $contract->advisor->commission_rate,
                        'commission_amount' => $commissionAmount,
                        'period_month' => $month,
                        'period_year' => $year,
                        'payment_status' => 'pending'
                    ]);
                    
                    $commissions[] = $commission;
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

        $baseAmount = $contract->total_price;
        $commissionRate = $contract->advisor->commission_rate / 100;
        
        // Aplicar diferentes tasas según el plan de pagos
        $multiplier = match($contract->payment_plan) {
            'cash' => 1.0,
            'installments_6' => 0.9,
            'installments_12' => 0.8,
            'installments_24' => 0.7,
            default => 1.0
        };

        return $baseAmount * $commissionRate * $multiplier;
    }

    public function payCommissions(array $commissionIds): bool
    {
        return DB::transaction(function () use ($commissionIds) {
            $updated = $this->commissionRepo->markMultipleAsPaid($commissionIds);
            return $updated > 0;
        });
    }

    public function getTopPerformers(int $month, int $year, int $limit = 10): Collection
    {
        return $this->employeeRepo->getTopPerformers($month, $year, $limit);
    }

    public function getAdvisorDashboard(int $employeeId, int $month, int $year): array
    {
        $employee = $this->employeeRepo->findById($employeeId);
        
        if (!$employee || !$employee->is_advisor) {
            throw new \Exception('Empleado no encontrado o no es asesor');
        }

        $monthlySales = $employee->calculateMonthlySales($month, $year);
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
                'goal' => $employee->individual_goal,
                'achievement_percentage' => round($employee->calculateGoalAchievement($month, $year), 2)
            ],
            'earnings_summary' => [
                'base_salary' => (float)$employee->base_salary,
                'commissions' => (float)$monthlyCommissions,
                'bonuses' =>(float) $monthlyBonuses,
                'total_estimated' => $employee->base_salary +
                    $monthlyCommissions +
                    $monthlyBonuses
            ],
            'performance' => [
                'ranking' => $ranking !== false ? $ranking + 1 : null,
                'total_advisors' => $topPerformers->count()
            ],
            'recent_contracts' => $monthlySales->take(5)->map(function ($contract) {
                return [
                    'contract_number' => $contract->contract_number,
                    'total_price' => $contract->total_price,
                    'sign_date' => $contract->sign_date->format('Y-m-d'),
                    'client_name' => $contract->client->first_name . ' ' . $contract->client->last_name
                ];
            })
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