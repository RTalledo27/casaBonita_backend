<?php

namespace Modules\HumanResources\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\HumanResources\Models\Bonus;
use Modules\HumanResources\Models\BonusGoal;
use Modules\HumanResources\Models\BonusType;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Office;
use Modules\HumanResources\Models\Team;
use Modules\HumanResources\Repositories\BonusRepository;
use Modules\HumanResources\Repositories\EmployeeRepository;

class BonusService
{
    public function __construct(
        protected BonusRepository $bonusRepo,
        protected EmployeeRepository $employeeRepo
    ) {}

    // =========================================================================
    // CREATE BONUS
    // =========================================================================

    /**
     * Crear bono basado en tipo y meta
     */
    public function createBonus(array $data): Bonus
    {
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
            'notes' => $description,
        ]);
    }

    // =========================================================================
    // AUTOMATIC BONUS PROCESSING
    // =========================================================================

    /**
     * Procesar todos los bonos automáticos para un período
     */
    public function processAllAutomaticBonuses(int $month, int $year, array $options = []): array
    {
        $allBonuses = [];
        $bonusTypeFilter = $options['bonus_type'] ?? null;
        $dryRun = $options['dry_run'] ?? false;

        $automaticBonusTypes = BonusType::active()->automatic()->get();

        foreach ($automaticBonusTypes as $bonusType) {
            if ($bonusTypeFilter && $bonusType->type_code !== $bonusTypeFilter) {
                continue;
            }

            try {
                switch ($bonusType->type_code) {
                    case 'INDIVIDUAL_GOAL':
                        $allBonuses['individual'] = $this->processIndividualGoalBonuses($month, $year, $dryRun);
                        break;

                    case 'TEAM_GOAL':
                        $allBonuses['team'] = $this->processTeamGoalBonuses($month, $year, $dryRun);
                        break;

                    case 'OFFICE_GOAL':
                        $allBonuses['office'] = $this->processOfficeGoalBonuses($month, $year, $dryRun);
                        break;

                    case 'QUARTERLY':
                        if (in_array($month, [3, 6, 9, 12])) {
                            $quarter = ceil($month / 3);
                            $allBonuses['quarterly'] = $this->processQuarterlyBonuses($quarter, $year, $dryRun);
                        }
                        break;

                    case 'BIWEEKLY':
                        $allBonuses['biweekly'] = $this->processBiweeklyBonuses($month, $year, 1, $dryRun);
                        break;

                    case 'COLLECTION':
                        $allBonuses['collection'] = $this->processCollectionBonuses($month, $year, $dryRun);
                        break;
                }
            } catch (\Exception $e) {
                Log::error("Error processing {$bonusType->type_code} bonuses: " . $e->getMessage());
                $allBonuses[$bonusType->type_code] = ['error' => $e->getMessage()];
            }
        }

        return $allBonuses;
    }

    // =========================================================================
    // 1. INDIVIDUAL GOAL BONUSES
    // =========================================================================

    /**
     * Procesar bonos por meta individual
     * Cada asesor vs su individual_goal (monto) → % de cumplimiento → bonus escalonado
     */
    public function processIndividualGoalBonuses(int $month, int $year, bool $dryRun = false): array
    {
        $bonuses = [];
        $bonusType = BonusType::where('type_code', 'INDIVIDUAL_GOAL')->active()->first();

        if (!$bonusType) {
            return $bonuses;
        }

        $processLogic = function () use ($month, $year, $bonusType, &$bonuses, $dryRun) {
            $employees = Employee::active()->get();

            foreach ($employees as $employee) {
                if (!$bonusType->isApplicableEmployee($employee)) {
                    continue;
                }

                // Skip if already has bonus for this period
                if ($this->hasExistingBonus($employee->employee_id, $bonusType->bonus_type_id, $month, $year)) {
                    continue;
                }

                // Calculate achievement: sales amount / individual_goal * 100
                $achievement = $employee->calculateGoalAchievement($month, $year);

                // Find the best matching goal tier
                $bonusGoal = $this->findApplicableBonusGoal($bonusType, $employee, $achievement);

                if (!$bonusGoal || $achievement < $bonusGoal->min_achievement) {
                    continue;
                }

                $bonusAmount = $bonusGoal->calculateBonusAmount($achievement, $employee->base_salary);

                if ($bonusAmount <= 0) {
                    continue;
                }

                $salesAmount = $employee->calculateMonthlySales($month, $year)->sum('total_price');

                if ($dryRun) {
                    $bonuses[] = [
                        'dry_run' => true,
                        'employee_id' => $employee->employee_id,
                        'employee_name' => $employee->full_name,
                        'achievement' => round($achievement, 2),
                        'goal_name' => $bonusGoal->goal_name,
                        'bonus_amount' => $bonusAmount,
                        'target' => $employee->individual_goal,
                        'achieved' => $salesAmount,
                    ];
                    continue;
                }

                $bonus = $this->bonusRepo->create([
                    'employee_id' => $employee->employee_id,
                    'bonus_type_id' => $bonusType->bonus_type_id,
                    'bonus_goal_id' => $bonusGoal->bonus_goal_id,
                    'bonus_name' => $bonusGoal->goal_name,
                    'bonus_amount' => $bonusAmount,
                    'target_amount' => $employee->individual_goal,
                    'achieved_amount' => $salesAmount,
                    'achievement_percentage' => round($achievement, 2),
                    'payment_status' => 'pendiente',
                    'period_month' => $month,
                    'period_year' => $year,
                    'created_by' => null,
                    'notes' => "Bono automático por meta individual ({$achievement}%) - {$bonusGoal->goal_name}",
                ]);

                $bonuses[] = $bonus;
            }
        };

        if ($dryRun) {
            $processLogic();
        } else {
            DB::transaction($processLogic);
        }

        return $bonuses;
    }

    // =========================================================================
    // 2. TEAM GOAL BONUSES
    // =========================================================================

    /**
     * Procesar bonos por meta de equipo
     * Cada Team tiene monthly_goal → suma ventas de miembros → % → bono para cada miembro
     */
    public function processTeamGoalBonuses(int $month, int $year, bool $dryRun = false): array
    {
        $bonuses = [];
        $bonusType = BonusType::where('type_code', 'TEAM_GOAL')->active()->first();

        if (!$bonusType) {
            return $bonuses;
        }

        $processLogic = function () use ($month, $year, $bonusType, &$bonuses, $dryRun) {
            // Iterate over active teams with a monthly_goal
            $teams = Team::active()->where('monthly_goal', '>', 0)->get();

            foreach ($teams as $team) {
                // Calculate team achievement
                $teamAchievement = $team->calculateMonthlyAchievement($month, $year);
                $teamSalesAmount = $team->calculateMonthlySalesAmount($month, $year);

                // Find the best matching goal for this team achievement
                $bonusGoal = $bonusType->bonusGoals()
                    ->active()
                    ->valid()
                    ->where('min_achievement', '<=', $teamAchievement)
                    ->forTeam($team->team_id)
                    ->orderBy('min_achievement', 'desc')
                    ->first();

                if (!$bonusGoal) {
                    continue;
                }

                // Give bonus to each eligible team member
                $members = $team->members()->active()->get();

                foreach ($members as $member) {
                    if (!$bonusType->isApplicableEmployee($member)) {
                        continue;
                    }

                    if ($this->hasExistingBonus($member->employee_id, $bonusType->bonus_type_id, $month, $year)) {
                        continue;
                    }

                    $bonusAmount = $bonusGoal->calculateBonusAmount($teamAchievement, $member->base_salary);

                    if ($bonusAmount <= 0) {
                        continue;
                    }

                    if ($dryRun) {
                        $bonuses[] = [
                            'dry_run' => true,
                            'employee_id' => $member->employee_id,
                            'employee_name' => $member->full_name,
                            'team' => $team->team_name,
                            'team_achievement' => round($teamAchievement, 2),
                            'goal_name' => $bonusGoal->goal_name,
                            'bonus_amount' => $bonusAmount,
                        ];
                        continue;
                    }

                    $bonus = $this->bonusRepo->create([
                        'employee_id' => $member->employee_id,
                        'bonus_type_id' => $bonusType->bonus_type_id,
                        'bonus_goal_id' => $bonusGoal->bonus_goal_id,
                        'bonus_name' => $bonusGoal->goal_name,
                        'bonus_amount' => $bonusAmount,
                        'target_amount' => $team->monthly_goal,
                        'achieved_amount' => $teamSalesAmount,
                        'achievement_percentage' => round($teamAchievement, 2),
                        'payment_status' => 'pendiente',
                        'period_month' => $month,
                        'period_year' => $year,
                        'created_by' => null,
                        'notes' => "Bono automático por meta de equipo '{$team->team_name}' ({$teamAchievement}%)",
                    ]);

                    $bonuses[] = $bonus;
                }
            }
        };

        if ($dryRun) {
            $processLogic();
        } else {
            DB::transaction($processLogic);
        }

        return $bonuses;
    }

    // =========================================================================
    // 3. OFFICE GOAL BONUSES
    // =========================================================================

    /**
     * Procesar bonos por meta de oficina
     * Cada Office tiene monthly_goal → suma ventas de empleados → % → bono para cada miembro
     */
    public function processOfficeGoalBonuses(int $month, int $year, bool $dryRun = false): array
    {
        $bonuses = [];
        $bonusType = BonusType::where('type_code', 'OFFICE_GOAL')->active()->first();

        if (!$bonusType) {
            return $bonuses;
        }

        $processLogic = function () use ($month, $year, $bonusType, &$bonuses, $dryRun) {
            $offices = Office::active()->where('monthly_goal', '>', 0)->get();

            foreach ($offices as $office) {
                $officeAchievement = $office->calculateMonthlyAchievement($month, $year);
                $officeSalesAmount = $office->calculateMonthlySalesAmount($month, $year);

                // Find the best matching goal for this office achievement
                $bonusGoal = $bonusType->bonusGoals()
                    ->active()
                    ->valid()
                    ->where('min_achievement', '<=', $officeAchievement)
                    ->forOffice($office->office_id)
                    ->orderBy('min_achievement', 'desc')
                    ->first();

                if (!$bonusGoal) {
                    continue;
                }

                // Give bonus to each eligible employee in the office
                $employees = $office->employees()->active()->get();

                foreach ($employees as $employee) {
                    if (!$bonusType->isApplicableEmployee($employee)) {
                        continue;
                    }

                    if ($this->hasExistingBonus($employee->employee_id, $bonusType->bonus_type_id, $month, $year)) {
                        continue;
                    }

                    $bonusAmount = $bonusGoal->calculateBonusAmount($officeAchievement, $employee->base_salary);

                    if ($bonusAmount <= 0) {
                        continue;
                    }

                    if ($dryRun) {
                        $bonuses[] = [
                            'dry_run' => true,
                            'employee_id' => $employee->employee_id,
                            'employee_name' => $employee->full_name,
                            'office' => $office->name,
                            'office_achievement' => round($officeAchievement, 2),
                            'goal_name' => $bonusGoal->goal_name,
                            'bonus_amount' => $bonusAmount,
                        ];
                        continue;
                    }

                    $bonus = $this->bonusRepo->create([
                        'employee_id' => $employee->employee_id,
                        'bonus_type_id' => $bonusType->bonus_type_id,
                        'bonus_goal_id' => $bonusGoal->bonus_goal_id,
                        'bonus_name' => $bonusGoal->goal_name,
                        'bonus_amount' => $bonusAmount,
                        'target_amount' => $office->monthly_goal,
                        'achieved_amount' => $officeSalesAmount,
                        'achievement_percentage' => round($officeAchievement, 2),
                        'payment_status' => 'pendiente',
                        'period_month' => $month,
                        'period_year' => $year,
                        'created_by' => null,
                        'notes' => "Bono automático por meta de oficina '{$office->name}' ({$officeAchievement}%)",
                    ]);

                    $bonuses[] = $bonus;
                }
            }
        };

        if ($dryRun) {
            $processLogic();
        } else {
            DB::transaction($processLogic);
        }

        return $bonuses;
    }

    // =========================================================================
    // 4. QUARTERLY BONUSES
    // =========================================================================

    /**
     * Procesar bonos trimestrales
     * Basado en cantidad de ventas acumuladas en el trimestre
     */
    public function processQuarterlyBonuses(int $quarter, int $year, bool $dryRun = false): array
    {
        $bonuses = [];
        $bonusType = BonusType::where('type_code', 'QUARTERLY')->active()->first();

        if (!$bonusType) {
            return $bonuses;
        }

        $processLogic = function () use ($quarter, $year, $bonusType, &$bonuses, $dryRun) {
            $employees = Employee::active()->get();

            foreach ($employees as $employee) {
                if (!$bonusType->isApplicableEmployee($employee)) {
                    continue;
                }

                // Check existing quarterly bonus
                $existingBonus = Bonus::where('employee_id', $employee->employee_id)
                    ->where('bonus_type_id', $bonusType->bonus_type_id)
                    ->where('period_quarter', $quarter)
                    ->where('period_year', $year)
                    ->first();

                if ($existingBonus) {
                    continue;
                }

                $quarterlySales = $this->getQuarterlySalesCount($employee, $quarter, $year);

                // For quarterly, min_achievement = minimum sales count needed
                $bonusGoal = $bonusType->bonusGoals()
                    ->active()
                    ->valid()
                    ->where('min_achievement', '<=', $quarterlySales)
                    ->forEmployeeType($employee->employee_type)
                    ->orderBy('min_achievement', 'desc')
                    ->first();

                if (!$bonusGoal) {
                    continue;
                }

                $bonusAmount = $bonusGoal->calculateBonusAmount($quarterlySales, $employee->base_salary);

                if ($bonusAmount <= 0) {
                    continue;
                }

                if ($dryRun) {
                    $bonuses[] = [
                        'dry_run' => true,
                        'employee_id' => $employee->employee_id,
                        'employee_name' => $employee->full_name,
                        'quarter' => $quarter,
                        'quarterly_sales' => $quarterlySales,
                        'goal_name' => $bonusGoal->goal_name,
                        'bonus_amount' => $bonusAmount,
                    ];
                    continue;
                }

                $month = $quarter * 3; // Last month of quarter for period_month
                $bonus = $this->bonusRepo->create([
                    'employee_id' => $employee->employee_id,
                    'bonus_type_id' => $bonusType->bonus_type_id,
                    'bonus_goal_id' => $bonusGoal->bonus_goal_id,
                    'bonus_name' => $bonusGoal->goal_name,
                    'bonus_amount' => $bonusAmount,
                    'target_amount' => $bonusGoal->target_value ?? $bonusGoal->min_achievement,
                    'achieved_amount' => $quarterlySales,
                    'achievement_percentage' => $bonusGoal->min_achievement > 0
                        ? round(($quarterlySales / $bonusGoal->min_achievement) * 100, 2)
                        : 0,
                    'payment_status' => 'pendiente',
                    'period_month' => $month,
                    'period_quarter' => $quarter,
                    'period_year' => $year,
                    'created_by' => null,
                    'notes' => "Bono automático trimestral Q{$quarter} {$year} - {$quarterlySales} ventas",
                ]);

                $bonuses[] = $bonus;
            }
        };

        if ($dryRun) {
            $processLogic();
        } else {
            DB::transaction($processLogic);
        }

        return $bonuses;
    }

    // =========================================================================
    // 5. BIWEEKLY BONUSES
    // =========================================================================

    /**
     * Procesar bonos quincenales
     * Basado en cantidad de ventas en la quincena
     */
    public function processBiweeklyBonuses(int $month, int $year, int $fortnight = 1, bool $dryRun = false): array
    {
        $bonuses = [];
        $bonusType = BonusType::where('type_code', 'BIWEEKLY')->active()->first();

        if (!$bonusType) {
            return $bonuses;
        }

        $processLogic = function () use ($month, $year, $fortnight, $bonusType, &$bonuses, $dryRun) {
            $employees = Employee::active()->get();

            foreach ($employees as $employee) {
                if (!$bonusType->isApplicableEmployee($employee)) {
                    continue;
                }

                // Check existing biweekly bonus (use notes to distinguish fortnights)
                $existingBonus = Bonus::where('employee_id', $employee->employee_id)
                    ->where('bonus_type_id', $bonusType->bonus_type_id)
                    ->where('period_month', $month)
                    ->where('period_year', $year)
                    ->where('notes', 'like', "%quincena {$fortnight}%")
                    ->first();

                if ($existingBonus) {
                    continue;
                }

                $salesCount = $employee->calculateFortnightlySalesCount($month, $year, $fortnight);

                // Find applicable goal (min_achievement = minimum sales count)
                $bonusGoal = $bonusType->bonusGoals()
                    ->active()
                    ->valid()
                    ->where('min_achievement', '<=', $salesCount)
                    ->forEmployeeType($employee->employee_type)
                    ->orderBy('min_achievement', 'desc')
                    ->first();

                if (!$bonusGoal) {
                    continue;
                }

                $targetCount = $bonusGoal->target_value ?? $bonusGoal->min_achievement;
                $bonusAmount = $bonusGoal->calculateBonusAmount($salesCount, $employee->base_salary);

                if ($bonusAmount <= 0) {
                    continue;
                }

                $achievement = $targetCount > 0 ? round(($salesCount / $targetCount) * 100, 2) : 0;

                if ($dryRun) {
                    $bonuses[] = [
                        'dry_run' => true,
                        'employee_id' => $employee->employee_id,
                        'employee_name' => $employee->full_name,
                        'fortnight' => $fortnight,
                        'sales_count' => $salesCount,
                        'target' => $targetCount,
                        'goal_name' => $bonusGoal->goal_name,
                        'bonus_amount' => $bonusAmount,
                    ];
                    continue;
                }

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
                    'notes' => "Bono automático quincena {$fortnight} - {$salesCount}/{$targetCount} ventas ({$achievement}%)",
                ]);

                $bonuses[] = $bonus;
            }
        };

        if ($dryRun) {
            $processLogic();
        } else {
            DB::transaction($processLogic);
        }

        return $bonuses;
    }

    // =========================================================================
    // 6. COLLECTION BONUSES
    // =========================================================================

    /**
     * Procesar bonos de recaudación
     * Basado en monto total de ventas del mes
     */
    public function processCollectionBonuses(int $month, int $year, bool $dryRun = false): array
    {
        $bonuses = [];
        $bonusType = BonusType::where('type_code', 'COLLECTION')->active()->first();

        if (!$bonusType) {
            return $bonuses;
        }

        $processLogic = function () use ($month, $year, $bonusType, &$bonuses, $dryRun) {
            $employees = Employee::active()->get();

            foreach ($employees as $employee) {
                if (!$bonusType->isApplicableEmployee($employee)) {
                    continue;
                }

                if ($this->hasExistingBonus($employee->employee_id, $bonusType->bonus_type_id, $month, $year)) {
                    continue;
                }

                // FIX: calculateMonthlySales returns a Collection → sum total_price
                $salesAmount = (float) $employee->calculateMonthlySales($month, $year)->sum('total_price');

                // Find applicable goal
                $bonusGoal = $bonusType->bonusGoals()
                    ->active()
                    ->valid()
                    ->forEmployeeType($employee->employee_type)
                    ->orderBy('min_achievement', 'desc')
                    ->first();

                if (!$bonusGoal) {
                    continue;
                }

                $targetAmount = $bonusGoal->target_value ?? 50000;
                $achievement = $targetAmount > 0 ? round(($salesAmount / $targetAmount) * 100, 2) : 0;

                if ($achievement < $bonusGoal->min_achievement) {
                    continue;
                }

                $bonusAmount = $bonusGoal->calculateBonusAmount($achievement, $employee->base_salary);

                if ($bonusAmount <= 0) {
                    continue;
                }

                if ($dryRun) {
                    $bonuses[] = [
                        'dry_run' => true,
                        'employee_id' => $employee->employee_id,
                        'employee_name' => $employee->full_name,
                        'sales_amount' => $salesAmount,
                        'target' => $targetAmount,
                        'achievement' => $achievement,
                        'goal_name' => $bonusGoal->goal_name,
                        'bonus_amount' => $bonusAmount,
                    ];
                    continue;
                }

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
                    'notes' => "Bono automático recaudación - S/" . number_format($salesAmount, 2) . "/S/" . number_format($targetAmount, 2) . " ({$achievement}%)",
                ]);

                $bonuses[] = $bonus;
            }
        };

        if ($dryRun) {
            $processLogic();
        } else {
            DB::transaction($processLogic);
        }

        return $bonuses;
    }

    // =========================================================================
    // APPROVAL & PAYMENT
    // =========================================================================

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

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Check if employee already has a bonus of this type for the period
     */
    private function hasExistingBonus(int $employeeId, int $bonusTypeId, int $month, int $year): bool
    {
        return Bonus::where('employee_id', $employeeId)
            ->where('bonus_type_id', $bonusTypeId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->exists();
    }

    /**
     * Encontrar meta de bono aplicable (el mejor tier que cumple)
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
            ->where('status', 'vigente')
            ->count();
    }

    // =========================================================================
    // DASHBOARD & REPORTING
    // =========================================================================

    /**
     * Obtener resumen de bonos por período
     */
    public function getBonusSummary(int $month, int $year): array
    {
        $bonuses = $this->bonusRepo->getAll([
            'period_month' => $month,
            'period_year' => $year,
        ]);

        return [
            'total_bonuses' => $bonuses->count(),
            'total_amount' => $bonuses->sum('bonus_amount'),
            'by_type' => $bonuses->groupBy('bonusType.type_name')->map(fn($group) => [
                'count' => $group->count(),
                'amount' => $group->sum('bonus_amount'),
            ]),
            'by_status' => [
                'pending' => $bonuses->where('payment_status', 'pendiente')->count(),
                'paid' => $bonuses->where('payment_status', 'pagado')->count(),
                'cancelled' => $bonuses->where('payment_status', 'cancelado')->count(),
            ],
            'pending_approval' => $bonuses->filter(fn($b) => $b->requiresApproval())->count(),
        ];
    }

    /**
     * Bonos para dashboard del empleado
     */
    public function getBonusesForDashboard(int $employeeId): array
    {
        $bonuses = $this->bonusRepo->getAll([
            'employee_id' => $employeeId,
            'payment_status' => ['pendiente', 'activo'],
        ]);

        return [
            'bonuses' => $bonuses->map(fn($bonus) => (new \Modules\HumanResources\Transformers\BonusResource($bonus))->forDashboard()),
            'total_bonuses' => $bonuses->count(),
            'total_amount' => $bonuses->sum('bonus_amount'),
            'by_type' => $bonuses->groupBy('bonusType.type_name')->map(fn($group) => [
                'count' => $group->count(),
                'amount' => $group->sum('bonus_amount'),
            ]),
            'by_status' => [
                'pending' => $bonuses->where('payment_status', 'pendiente')->count(),
                'paid' => $bonuses->where('payment_status', 'pagado')->count(),
                'cancelled' => $bonuses->where('payment_status', 'cancelado')->count(),
            ],
            'pending_approval' => $bonuses->filter(fn($b) => $b->requiresApproval())->count(),
        ];
    }

    /**
     * Bonos para dashboard de admin
     */
    public function getBonusesForAdminDashboard(int $month, int $year): array
    {
        $bonuses = $this->bonusRepo->getAll([
            'period_month' => $month,
            'period_year' => $year,
        ]);

        return [
            'total_bonuses' => $bonuses->count(),
            'total_amount' => $bonuses->sum('bonus_amount'),
            'by_type' => $bonuses->groupBy('bonusType.type_name')->map(fn($group) => [
                'count' => $group->count(),
                'amount' => $group->sum('bonus_amount'),
            ]),
            'by_status' => [
                'pending' => $bonuses->where('payment_status', 'pendiente')->count(),
                'paid' => $bonuses->where('payment_status', 'pagado')->count(),
                'cancelled' => $bonuses->where('payment_status', 'cancelado')->count(),
            ],
            'pending_approval' => $bonuses->filter(fn($b) => $b->requiresApproval())->count(),
        ];
    }
}
