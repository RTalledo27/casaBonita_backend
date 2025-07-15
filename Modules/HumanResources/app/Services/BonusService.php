<?php

namespace Modules\HumanResources\Services;

use Modules\HumanResources\Models\Bonus;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Team;
use Modules\HumanResources\Repositories\BonusRepository;

class BonusService
{
    public function __construct(protected BonusRepository $bonusRepo) {}

    public function calculateIndividualBonuses(int $month, int $year): array
    {
        $employees = Employee::advisors()->active()->get();
        $bonuses = [];

        foreach ($employees as $employee) {
            $achievement = $employee->calculateGoalAchievement($month, $year);
            $bonusAmount = Bonus::calculateIndividualGoalBonus($achievement);

            if ($bonusAmount > 0) {
                $bonus = $this->bonusRepo->create([
                    'employee_id' => $employee->employee_id,
                    'bonus_type' => 'meta_individual',
                    'bonus_name' => 'Bono por Meta Individual',
                    'bonus_amount' => $bonusAmount,
                    'target_amount' => $employee->individual_goal,
                    'achieved_amount' => $employee->calculateMonthlySales($month, $year)->sum('total_price'),
                    'achievement_percentage' => $achievement,
                    'payment_status' => 'pendiente',
                    'period_month' => $month,
                    'period_year' => $year,
                    'notes' => "Bono por cumplimiento de meta individual ({$achievement}%)"
                ]);
                $bonuses[] = $bonus;
            }
        }

        return $bonuses;
    }

    public function calculateTeamBonuses(int $month, int $year): array
    {
        $teams = Team::active()->get();
        $bonuses = [];

        foreach ($teams as $team) {
            $achievement = $team->calculateMonthlyAchievement($month, $year);

            foreach ($team->members()->where('employee_type', 'vendedor')->get() as $member) {
                $bonusAmount = Bonus::calculateTeamGoalBonus($achievement, $member->employee_type);

                if ($bonusAmount > 0) {
                    $bonus = $this->bonusRepo->create([
                        'employee_id' => $member->employee_id,
                        'bonus_type' => 'meta_equipo',
                        'bonus_name' => 'Bono por Meta de Equipo',
                        'bonus_amount' => $bonusAmount,
                        'target_amount' => $team->monthly_goal,
                        'achieved_amount' => $team->members->sum(function ($m) use ($month, $year) {
                            return $m->calculateMonthlySales($month, $year)->sum('total_price');
                        }),
                        'achievement_percentage' => $achievement,
                        'payment_status' => 'pendiente',
                        'period_month' => $month,
                        'period_year' => $year,
                        'notes' => "Bono por cumplimiento de meta de equipo {$team->team_name} ({$achievement}%)"
                    ]);
                    $bonuses[] = $bonus;
                }
            }
        }

        return $bonuses;
    }

    public function calculateQuarterlyBonuses(int $quarter, int $year): array
    {
        $employees = Employee::where('employee_type', 'asesor_inmobiliario')->active()->get();
        $bonuses = [];

        foreach ($employees as $employee) {
            $quarterSales = $this->getQuarterlySales($employee, $quarter, $year);
            $bonusAmount = Bonus::calculateQuarterlyBonus($quarterSales, $employee->employee_type);

            if ($bonusAmount > 0) {
                $bonus = $this->bonusRepo->create([
                    'employee_id' => $employee->employee_id,
                    'bonus_type' => 'trimestral',
                    'bonus_name' => 'Bono Trimestral',
                    'bonus_amount' => $bonusAmount,
                    'target_amount' => 30,
                    'achieved_amount' => $quarterSales,
                    'achievement_percentage' => ($quarterSales / 30) * 100,
                    'payment_status' => 'pendiente',
                    'period_quarter' => $quarter,
                    'period_year' => $year,
                    'notes' => "Bono trimestral por {$quarterSales} ventas"
                ]);
                $bonuses[] = $bonus;
            }
        }

        return $bonuses;
    }

    public function calculateBiweeklyBonuses(int $month, int $year, int $fortnight): array
    {
        $employees = Employee::where('employee_type', 'asesor_inmobiliario')->active()->get();
        $bonuses = [];

        foreach ($employees as $employee) {
            $biweeklySales = $this->getBiweeklySales($employee, $month, $year, $fortnight);
            $bonusAmount = Bonus::calculateBiweeklyBonus($biweeklySales, $employee->employee_type);

            if ($bonusAmount > 0) {
                $bonus = $this->bonusRepo->create([
                    'employee_id' => $employee->employee_id,
                    'bonus_type' => 'quincenal',
                    'bonus_name' => 'Bono Quincenal',
                    'bonus_amount' => $bonusAmount,
                    'target_amount' => 6,
                    'achieved_amount' => $biweeklySales,
                    'achievement_percentage' => ($biweeklySales / 6) * 100,
                    'payment_status' => 'pendiente',
                    'period_month' => $month,
                    'period_year' => $year,
                    'notes' => "Bono quincenal por {$biweeklySales} ventas"
                ]);
                $bonuses[] = $bonus;
            }
        }

        return $bonuses;
    }

    protected function getQuarterlySales(Employee $employee, int $quarter, int $year): int
    {
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $quarter * 3;

        $totalSales = 0;
        for ($month = $startMonth; $month <= $endMonth; $month++) {
            $totalSales += $employee->calculateMonthlySales($month, $year)->count();
        }

        return $totalSales;
    }

    protected function getBiweeklySales(Employee $employee, int $month, int $year, int $fortnight): int
    {
        $startDay = $fortnight === 1 ? 1 : 16;
        $endDay = $fortnight === 1 ? 15 : 31;

        return $employee->contracts()
            ->whereMonth('sign_date', $month)
            ->whereYear('sign_date', $year)
            ->whereDay('sign_date', '>=', $startDay)
            ->whereDay('sign_date', '<=', $endDay)
            ->where('status', 'aprobado')
            ->count();
    }
}
