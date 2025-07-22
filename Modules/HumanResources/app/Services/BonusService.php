<?php

namespace Modules\HumanResources\Services;

use Illuminate\Support\Facades\DB;
use Modules\HumanResources\Models\Bonus;
use Modules\HumanResources\Models\BonusGoal;
use Modules\HumanResources\Models\BonusType;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Team;
use Modules\HumanResources\Repositories\BonusRepository;
use Modules\HumanResources\Repositories\EmployeeRepository;

class BonusService
{
    public function __construct(
        protected BonusRepository $bonusRepo,
        protected EmployeeRepository $employeeRepo
    ) {}

    /**
     * Crear bono basado en tipo y meta
     */
    public function createBonus(array $data): Bonus
    {
        // Si se especifica un tipo de bono, buscar la meta apropiada
        if (isset($data['bonus_type_id']) && !isset($data['bonus_goal_id'])) {
            $bonusType = BonusType::find($data['bonus_type_id']);
            $employee = Employee::find($data['employee_id']);

            if ($bonusType && $employee) {
                $bonusGoal = $this->findApplicableBonusGoal($bonusType, $employee, $data['achievement_percentage'] ?? 0);
                if ($bonusGoal) {
                    $data['bonus_goal_id'] = $bonusGoal->bonus_goal_id;
                    $data['bonus_amount'] = $bonusGoal->calculateBonusAmount(
                        $data['achievement_percentage'] ?? 0,
                        $employee->base_salary
                    );
                }
            }
        }

        return $this->bonusRepo->create($data);
    }

    /**
     * Procesar bonos automáticos para un período
     */
    public function processAllAutomaticBonuses(int $month, int $year): array
    {
        $allBonuses = [];

        // Obtener tipos de bonos automáticos activos
        $automaticBonusTypes = BonusType::active()->automatic()->get();

        foreach ($automaticBonusTypes as $bonusType) {
            switch ($bonusType->type_code) {
                case 'INDIVIDUAL_GOAL':
                    $bonuses = $this->processIndividualGoalBonuses($month, $year);
                    $allBonuses['individual'] = $bonuses;
                    break;

                case 'TEAM_GOAL':
                    $bonuses = $this->processTeamGoalBonuses($month, $year);
                    $allBonuses['team'] = $bonuses;
                    break;

                case 'QUARTERLY':
                    if (in_array($month, [3, 6, 9, 12])) {
                        $quarter = ceil($month / 3);
                        $bonuses = $this->processQuarterlyBonuses($quarter, $year);
                        $allBonuses['quarterly'] = $bonuses;
                    }
                    break;

                case 'BIWEEKLY':
                    $bonuses = $this->processBiweeklyBonuses($month, $year);
                    $allBonuses['biweekly'] = $bonuses;
                    break;

                case 'COLLECTION':
                    $bonuses = $this->processCollectionBonuses($month, $year);
                    $allBonuses['collection'] = $bonuses;
                    break;
            }
        }

        return $allBonuses;
    }

    /**
     * Procesar bonos por meta individual
     */
    public function processIndividualGoalBonuses(int $month, int $year): array
    {
        $bonuses = [];
        $bonusType = BonusType::where('type_code', 'INDIVIDUAL_GOAL')->active()->first();

        if (!$bonusType) {
            return $bonuses;
        }

        DB::transaction(function () use ($month, $year, $bonusType, &$bonuses) {
            $employees = $this->employeeRepo->getAll(['employment_status' => 'activo']);

            foreach ($employees as $employee) {
                if (!$bonusType->isApplicableToEmployee($employee)) {
                    continue;
                }

                // Verificar si ya tiene bono de este tipo para el período
                $existingBonus = $this->bonusRepo->getAll([
                    'employee_id' => $employee->employee_id,
                    'bonus_type_id' => $bonusType->bonus_type_id,
                    'period_month' => $month,
                    'period_year' => $year
                ])->first();

                if ($existingBonus) {
                    continue;
                }

                $achievement = $employee->calculateGoalAchievement($month, $year);
                $bonusGoal = $this->findApplicableBonusGoal($bonusType, $employee, $achievement);

                // Para metas basadas en CANTIDAD de ventas (como tu ejemplo de 10 ventas)
                if ($bonusGoal && $bonusGoal->goal_name && str_contains(strtolower($bonusGoal->goal_name), 'cantidad')) {
                    // Usar cantidad de ventas en lugar de monto
                    $salesCount = $employee->calculateMonthlySalesCount($month, $year);
                    $targetCount = $bonusGoal->target_value ?? 10; // Meta de cantidad (ej: 10 ventas)
                    $achievement = $targetCount > 0 ? ($salesCount / $targetCount) * 100 : 0;
                    
                    if ($achievement >= $bonusGoal->min_achievement) {
                        $bonusAmount = $bonusGoal->calculateBonusAmount($achievement, $employee->base_salary);

                        if ($bonusAmount > 0) {
                            $bonus = $this->bonusRepo->create([
                                'employee_id' => $employee->employee_id,
                                'bonus_type_id' => $bonusType->bonus_type_id,
                                'bonus_goal_id' => $bonusGoal->bonus_goal_id,
                                'bonus_name' => $bonusGoal->goal_name,
                                'bonus_amount' => $bonusAmount,
                                'target_amount' => $targetCount,
                                'achieved_amount' => $salesCount,
                                'achievement_percentage' => $achievement,
                                'payment_status' => 'pendiente',
                                'period_month' => $month,
                                'period_year' => $year,
                                'created_by' => null,
                                'notes' => "Bono automático por cantidad de ventas: {$salesCount}/{$targetCount} ({$achievement}%)"
                            ]);

                            $bonuses[] = $bonus;
                        }
                    }
                } else if ($bonusGoal && $achievement >= $bonusGoal->min_achievement) {
                    // Lógica original para metas basadas en MONTO
                    $bonusAmount = $bonusGoal->calculateBonusAmount($achievement, $employee->base_salary);

                    if ($bonusAmount > 0) {
                        $bonus = $this->bonusRepo->create([
                            'employee_id' => $employee->employee_id,
                            'bonus_type_id' => $bonusType->bonus_type_id,
                            'bonus_goal_id' => $bonusGoal->bonus_goal_id,
                            'bonus_name' => $bonusGoal->goal_name,
                            'bonus_amount' => $bonusAmount,
                            'target_amount' => $employee->individual_goal,
                            'achieved_amount' => $employee->calculateMonthlySales($month, $year)->sum('total_price'),
                            'achievement_percentage' => $achievement,
                            'payment_status' => 'pendiente',
                            'period_month' => $month,
                            'period_year' => $year,
                            'created_by' => null, // Sistema automático
                            'notes' => "Bono automático por cumplimiento de meta individual ({$achievement}%)"
                        ]);

                        $bonuses[] = $bonus;
                    }
                }
            }
        });

        return $bonuses;
    }

    /**
     * Procesar bonos por meta de equipo
     */
    public function processTeamGoalBonuses(int $month, int $year): array
    {
        $bonuses = [];
        $bonusType = BonusType::where('type_code', 'TEAM_GOAL')->active()->first();

        if (!$bonusType) {
            return $bonuses;
        }

        DB::transaction(function () use ($month, $year, $bonusType, &$bonuses) {
            // Obtener empleados que ya tienen bono individual este mes
            $employeesWithIndividualBonus = $this->bonusRepo->getAll([
                'period_month' => $month,
                'period_year' => $year
            ])->whereHas('bonusType', function ($q) {
                $q->where('type_code', 'INDIVIDUAL_GOAL');
            })->pluck('employee_id')->toArray();

            if (empty($employeesWithIndividualBonus)) {
                return;
            }

            // Calcular cumplimiento de meta de sucursal/empresa
            $branchAchievement = $this->calculateBranchGoalAchievement($month, $year);
            $bonusGoal = $this->findApplicableBonusGoal($bonusType, null, $branchAchievement);

            if ($bonusGoal && $branchAchievement >= $bonusGoal->min_achievement) {
                foreach ($employeesWithIndividualBonus as $employeeId) {
                    $employee = Employee::find($employeeId);

                    if (!$employee || !$bonusType->isApplicableToEmployee($employee)) {
                        continue;
                    }

                    // Verificar si ya tiene bono de equipo
                    $existingBonus = $this->bonusRepo->getAll([
                        'employee_id' => $employeeId,
                        'bonus_type_id' => $bonusType->bonus_type_id,
                        'period_month' => $month,
                        'period_year' => $year
                    ])->first();

                    if ($existingBonus) {
                        continue;
                    }

                    $bonusAmount = $bonusGoal->calculateBonusAmount($branchAchievement, $employee->base_salary);

                    if ($bonusAmount > 0) {
                        $bonus = $this->bonusRepo->create([
                            'employee_id' => $employeeId,
                            'bonus_type_id' => $bonusType->bonus_type_id,
                            'bonus_goal_id' => $bonusGoal->bonus_goal_id,
                            'bonus_name' => $bonusGoal->goal_name,
                            'bonus_amount' => $bonusAmount,
                            'target_amount' => null,
                            'achieved_amount' => null,
                            'achievement_percentage' => $branchAchievement,
                            'payment_status' => 'pendiente',
                            'period_month' => $month,
                            'period_year' => $year,
                            'created_by' => null,
                            'notes' => "Bono automático por meta de equipo ({$branchAchievement}% cumplimiento sucursal)"
                        ]);

                        $bonuses[] = $bonus;
                    }
                }
            }
        });

        return $bonuses;
    }

    /**
     * Procesar bonos trimestrales
     */
    public function processQuarterlyBonuses(int $quarter, int $year): array
    {
        $bonuses = [];
        $bonusType = BonusType::where('type_code', 'QUARTERLY')->active()->first();

        if (!$bonusType) {
            return $bonuses;
        }

        DB::transaction(function () use ($quarter, $year, $bonusType, &$bonuses) {
            $employees = $this->employeeRepo->getAll(['employment_status' => 'activo']);

            foreach ($employees as $employee) {
                if (!$bonusType->isApplicableToEmployee($employee)) {
                    continue;
                }

                // Verificar si ya tiene bono trimestral
                $existingBonus = $this->bonusRepo->getAll([
                    'employee_id' => $employee->employee_id,
                    'bonus_type_id' => $bonusType->bonus_type_id,
                    'period_quarter' => $quarter,
                    'period_year' => $year
                ])->first();

                if ($existingBonus) {
                    continue;
                }

                $quarterlySales = $this->getQuarterlySalesCount($employee, $quarter, $year);
                $bonusGoal = $this->findApplicableBonusGoal($bonusType, $employee, $quarterlySales);

                if ($bonusGoal && $quarterlySales >= $bonusGoal->min_achievement) {
                    $bonusAmount = $bonusGoal->calculateBonusAmount($quarterlySales, $employee->base_salary);

                    if ($bonusAmount > 0) {
                        $bonus = $this->bonusRepo->create([
                            'employee_id' => $employee->employee_id,
                            'bonus_type_id' => $bonusType->bonus_type_id,
                            'bonus_goal_id' => $bonusGoal->bonus_goal_id,
                            'bonus_name' => $bonusGoal->goal_name,
                            'bonus_amount' => $bonusAmount,
                            'target_amount' => $bonusGoal->min_achievement,
                            'achieved_amount' => $quarterlySales,
                            'achievement_percentage' => ($quarterlySales / $bonusGoal->min_achievement) * 100,
                            'payment_status' => 'pendiente',
                            'period_quarter' => $quarter,
                            'period_year' => $year,
                            'created_by' => null,
                            'notes' => "Bono automático trimestral Q{$quarter} {$year} - {$quarterlySales} ventas"
                        ]);

                        $bonuses[] = $bonus;
                    }
                }
            }
        });

        return $bonuses;
    }

    /**
     * Crear bono especial manual
     */
    public function createSpecialBonus(int $employeeId, float $amount, string $description, int $createdBy): Bonus
    {
        $bonusType = BonusType::where('type_code', 'SPECIAL')->active()->first();
        $bonusGoal = $bonusType ? $bonusType->bonusGoals()->active()->first() : null;

        return $this->bonusRepo->create([
            'employee_id' => $employeeId,
            'bonus_type_id' => $bonusType?->bonus_type_id,
            'bonus_goal_id' => $bonusGoal?->bonus_goal_id,
            'bonus_name' => 'Bono Especial',
            'bonus_amount' => $amount,
            'target_amount' => null,
            'achieved_amount' => null,
            'achievement_percentage' => null,
            'payment_status' => 'pendiente',
            'period_month' => now()->month,
            'period_year' => now()->year,
            'created_by' => $createdBy,
            'notes' => $description
        ]);
    }

    /**
     * Pagar múltiples bonos
     */
    public function payBonuses(array $bonusIds): bool
    {
        return DB::transaction(function () use ($bonusIds) {
            $bonuses = Bonus::whereIn('bonus_id', $bonusIds)
                ->where('payment_status', 'pendiente')
                ->get();

            $updated = 0;
            foreach ($bonuses as $bonus) {
                if ($bonus->canBePaid() && $bonus->markAsPaid()) {
                    $updated++;
                }
            }

            return $updated > 0;
        });
    }

    /**
     * Aprobar bonos que requieren aprobación
     */
    public function approveBonuses(array $bonusIds, Employee $approver): bool
    {
        return DB::transaction(function () use ($bonusIds, $approver) {
            $bonuses = Bonus::whereIn('bonus_id', $bonusIds)
                ->whereNull('approved_at')
                ->get();

            $approved = 0;
            foreach ($bonuses as $bonus) {
                if ($bonus->approve($approver)) {
                    $approved++;
                }
            }

            return $approved > 0;
        });
    }

    /**
     * Encontrar meta de bono aplicable
     */
    private function findApplicableBonusGoal(BonusType $bonusType, ?Employee $employee, float $achievement): ?BonusGoal
    {
        $query = $bonusType->bonusGoals()
            ->active()
            ->valid()
            ->where('min_achievement', '<=', $achievement)
            ->orderBy('min_achievement', 'desc');

        if ($employee) {
            $query->forEmployeeType($employee->employee_type)
                ->forTeam($employee->team_id);
        }

        return $query->first();
    }

    /**
     * Calcular cumplimiento de meta de sucursal/empresa
     */
    private function calculateBranchGoalAchievement(int $month, int $year): float
    {
        $totalGoal = $this->employeeRepo->getAll(['employment_status' => 'activo'])
            ->sum('individual_goal');

        if ($totalGoal <= 0) {
            return 0;
        }

        $totalSales = DB::table('contracts')
            ->whereYear('sign_date', $year)
            ->whereMonth('sign_date', $month)
            ->where('status', 'aprobado')
            ->sum('total_price');

        return ($totalSales / $totalGoal) * 100;
    }

    /**
     * Obtener cantidad de ventas trimestrales
     */
    private function getQuarterlySalesCount(Employee $employee, int $quarter, int $year): int
    {
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $quarter * 3;

        return $employee->contracts()
            ->whereYear('sign_date', $year)
            ->whereMonth('sign_date', '>=', $startMonth)
            ->whereMonth('sign_date', '<=', $endMonth)
            ->where('status', 'aprobado')
            ->count();
    }

    /**
     * Procesar bonos quincenales
     */
    public function processBiweeklyBonuses(int $month, int $year, int $fortnight = 1): array
    {
        $bonuses = [];
        $bonusType = BonusType::where('type_code', 'BIWEEKLY')->active()->first();

        if (!$bonusType) {
            return $bonuses;
        }

        DB::transaction(function () use ($month, $year, $fortnight, $bonusType, &$bonuses) {
            $employees = $this->employeeRepo->getAll(['employment_status' => 'activo']);

            foreach ($employees as $employee) {
                if (!$bonusType->isApplicableToEmployee($employee)) {
                    continue;
                }

                // Verificar si ya tiene bono quincenal para este período
                $existingBonus = $this->bonusRepo->getAll([
                    'employee_id' => $employee->employee_id,
                    'bonus_type_id' => $bonusType->bonus_type_id,
                    'period_month' => $month,
                    'period_year' => $year
                ])->where('notes', 'like', "%quincena {$fortnight}%")->first();

                if ($existingBonus) {
                    continue;
                }

                // Buscar BonusGoal quincenal
                $bonusGoal = $bonusType->bonusGoals()
                    ->active()
                    ->valid()
                    ->where('goal_name', 'like', '%quincenal%')
                    ->forEmployeeType($employee->employee_type)
                    ->first();

                if ($bonusGoal) {
                    $salesCount = $employee->calculateFortnightlySalesCount($month, $year, $fortnight);
                    $targetCount = $bonusGoal->target_value ?? 6; // Meta quincenal por defecto
                    $achievement = $targetCount > 0 ? ($salesCount / $targetCount) * 100 : 0;

                    if ($achievement >= $bonusGoal->min_achievement) {
                        $bonusAmount = $bonusGoal->calculateBonusAmount($achievement, $employee->base_salary);

                        if ($bonusAmount > 0) {
                            $bonus = $this->bonusRepo->create([
                                'employee_id' => $employee->employee_id,
                                'bonus_type_id' => $bonusType->bonus_type_id,
                                'bonus_goal_id' => $bonusGoal->bonus_goal_id,
                                'bonus_name' => $bonusGoal->goal_name,
                                'bonus_amount' => $bonusAmount,
                                'target_amount' => $targetCount,
                                'achieved_amount' => $salesCount,
                                'achievement_percentage' => $achievement,
                                'payment_status' => 'pendiente',
                                'period_month' => $month,
                                'period_year' => $year,
                                'created_by' => null,
                                'notes' => "Bono automático quincena {$fortnight} - {$salesCount}/{$targetCount} ventas ({$achievement}%)"
                            ]);

                            $bonuses[] = $bonus;
                        }
                    }
                }
            }
        });

        return $bonuses;
    }

    /**
     * Procesar bonos de recaudación
     */
    public function processCollectionBonuses(int $month, int $year): array
    {
        $bonuses = [];
        $bonusType = BonusType::where('type_code', 'COLLECTION')->active()->first();

        if (!$bonusType) {
            return $bonuses;
        }

        DB::transaction(function () use ($month, $year, $bonusType, &$bonuses) {
            $employees = $this->employeeRepo->getAll(['employment_status' => 'activo']);

            foreach ($employees as $employee) {
                if (!$bonusType->isApplicableToEmployee($employee)) {
                    continue;
                }

                // Verificar si ya tiene bono de recaudación para este período
                $existingBonus = $this->bonusRepo->getAll([
                    'employee_id' => $employee->employee_id,
                    'bonus_type_id' => $bonusType->bonus_type_id,
                    'period_month' => $month,
                    'period_year' => $year
                ])->first();

                if ($existingBonus) {
                    continue;
                }

                // Buscar BonusGoal de recaudación
                $bonusGoal = $bonusType->bonusGoals()
                    ->active()
                    ->valid()
                    ->where('goal_name', 'like', '%recaudacion%')
                    ->forEmployeeType($employee->employee_type)
                    ->first();

                if ($bonusGoal) {
                    $salesAmount = $employee->calculateMonthlySales($month, $year);
                    $targetAmount = $bonusGoal->target_value ?? 500000; // Meta de recaudación por defecto (ventas)
                    $achievement = $targetAmount > 0 ? ($salesAmount / $targetAmount) * 100 : 0;

                    if ($achievement >= $bonusGoal->min_achievement) {
                        $bonusAmount = $bonusGoal->calculateBonusAmount($achievement, $employee->base_salary);

                        if ($bonusAmount > 0) {
                            $bonus = $this->bonusRepo->create([
                                'employee_id' => $employee->employee_id,
                                'bonus_type_id' => $bonusType->bonus_type_id,
                                'bonus_goal_id' => $bonusGoal->bonus_goal_id,
                                'bonus_name' => $bonusGoal->goal_name,
                                'bonus_amount' => $bonusAmount,
                                'target_amount' => $targetAmount,
                                'achieved_amount' => $salesAmount,
                                'achievement_percentage' => $achievement,
                                'payment_status' => 'pendiente',
                                'period_month' => $month,
                                'period_year' => $year,
                                'created_by' => null,
                                'notes' => "Bono automático recaudación - $" . number_format($salesAmount, 2) . "/$" . number_format($targetAmount, 2) . " ({$achievement}%)"
                            ]);

                            $bonuses[] = $bonus;
                        }
                    }
                }
            }
        });

        return $bonuses;
    }

    /**
     * Obtener resumen de bonos por período
     */
    public function getBonusSummary(int $month, int $year): array
    {
        $bonuses = $this->bonusRepo->getAll([
            'period_month' => $month,
            'period_year' => $year
        ]);

        return [
            'total_bonuses' => $bonuses->count(),
            'total_amount' => $bonuses->sum('bonus_amount'),
            'by_type' => $bonuses->groupBy('bonusType.type_name')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('bonus_amount')
                ];
            }),
            'by_status' => [
                'pending' => $bonuses->where('payment_status', 'pendiente')->count(),
                'paid' => $bonuses->where('payment_status', 'pagado')->count(),
                'cancelled' => $bonuses->where('payment_status', 'cancelado')->count()
            ],
            'pending_approval' => $bonuses->filter(function ($bonus) {
                return $bonus->requiresApproval();
            })->count()
        ];
    }

    public function getBonusesForDashboard(int $employeeId): array
    {
        $bonuses = $this->bonusRepo->getAll([
            'employee_id' => $employeeId,
            'payment_status' => ['pendiente', 'activo']
        ]);

        return [
            'bonuses' => $bonuses->map(fn($bonus) => (new \Modules\HumanResources\Transformers\BonusResource($bonus))->forDashboard()),
            'total_bonuses' => $bonuses->count(),
            'total_amount' => $bonuses->sum('bonus_amount'),
            'by_type' => $bonuses->groupBy('bonusType.type_name')->map(fn($group) => [
                'count' => $group->count(),
                'amount' => $group->sum('bonus_amount')
            ]),
            'by_status' => [
                'pending' => $bonuses->where('payment_status', 'pendiente')->count(),
                'paid' => $bonuses->where('payment_status', 'pagado')->count(),
                'cancelled' => $bonuses->where('payment_status', 'cancelado')->count()
            ],
            'pending_approval' => $bonuses->filter(fn($bonus) => $bonus->requiresApproval())->count()
        ];
    }

    public function getBonusesForAdminDashboard(int $month, int $year): array
    {
        $bonuses = $this->bonusRepo->getAll([
            'period_month' => $month,
            'period_year' => $year
        ]);

        return [
            'total_bonuses' => $bonuses->count(),
            'total_amount' => $bonuses->sum('bonus_amount'),
            'by_type' => $bonuses->groupBy('bonusType.type_name')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('bonus_amount')
                ];
            }),
            'by_status' => [
                'pending' => $bonuses->where('payment_status', 'pendiente')->count(),
                'paid' => $bonuses->where('payment_status', 'pagado')->count(),
                'cancelled' => $bonuses->where('payment_status', 'cancelado')->count()
            ],
            'pending_approval' => $bonuses->filter(function ($bonus) {
                return $bonus->requiresApproval();
            })->count()

        ];
    }
}