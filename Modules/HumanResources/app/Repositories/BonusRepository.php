<?php

namespace Modules\HumanResources\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\HumanResources\Models\Bonus;

class BonusRepository
{
    public function __construct(
        protected Bonus $model
    ) {}

    public function getAll(array $filters = []): Collection
    {
        $query = $this->model->with([
            'employee.user', 
            'bonusType', 
            'bonusGoal', 
            'creator.user', 
            'approver.user'
        ]);

        $this->applyFilters($query, $filters);

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with([
            'employee.user', 
            'bonusType', 
            'bonusGoal', 
            'creator.user', 
            'approver.user'
        ]);

        $this->applyFilters($query, $filters);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function findById(int $id): ?Bonus
    {
        return $this->model->with([
            'employee.user', 
            'employee.team',
            'bonusType', 
            'bonusGoal', 
            'creator.user', 
            'approver.user'
        ])->find($id);
    }

    public function create(array $data): Bonus
    {
        $bonus = $this->model->create($data);
        return $this->findById($bonus->bonus_id);
    }

    public function update(int $id, array $data): ?Bonus
    {
        $bonus = $this->model->find($id);
        if (!$bonus) {
            return null;
        }

        $bonus->update($data);
        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $bonus = $this->model->find($id);
        if (!$bonus) {
            return false;
        }

        return $bonus->delete();
    }

    public function getPendingForPeriod(int $month, int $year): Collection
    {
        return $this->model->with(['employee.user', 'bonusType'])
                          ->pending()
                          ->byPeriod($month, $year)
                          ->get();
    }

    public function getPendingApproval(): Collection
    {
        return $this->model->with([
            'employee.user', 
            'bonusType', 
            'creator.user'
        ])
        ->pendingApproval()
        ->orderBy('created_at', 'asc')
        ->get();
    }

    public function getByEmployee(int $employeeId, array $filters = []): Collection
    {
        $query = $this->model->with(['bonusType', 'bonusGoal', 'creator.user', 'approver.user'])
                            ->where('employee_id', $employeeId);

        if (isset($filters['period_month']) && isset($filters['period_year'])) {
            $query->byPeriod($filters['period_month'], $filters['period_year']);
        }

        if (isset($filters['period_quarter']) && isset($filters['period_year'])) {
            $query->byQuarter($filters['period_quarter'], $filters['period_year']);
        }

        if (isset($filters['bonus_type_id'])) {
            $query->byType($filters['bonus_type_id']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getByType(int $bonusTypeId, array $filters = []): Collection
    {
        $query = $this->model->with(['employee.user', 'bonusGoal'])
                            ->byType($bonusTypeId);

        if (isset($filters['period_month']) && isset($filters['period_year'])) {
            $query->byPeriod($filters['period_month'], $filters['period_year']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function markMultipleAsPaid(array $bonusIds): int
    {
        return $this->model->whereIn('bonus_id', $bonusIds)
                          ->where('payment_status', 'pendiente')
                          ->update([
                              'payment_status' => 'pagado',
                              'payment_date' => now()->toDateString()
                          ]);
    }

    public function getTotalBonusesForPeriod(int $month, int $year): float
    {
        return $this->model->byPeriod($month, $year)
                          ->sum('bonus_amount');
    }

    public function getTotalBonusesForQuarter(int $quarter, int $year): float
    {
        return $this->model->byQuarter($quarter, $year)
                          ->sum('bonus_amount');
    }

    public function getBonusStatsByEmployee(int $employeeId): array
    {
        $bonuses = $this->model->where('employee_id', $employeeId)->get();

        return [
            'total_bonuses' => $bonuses->count(),
            'total_amount' => $bonuses->sum('bonus_amount'),
            'paid_amount' => $bonuses->where('payment_status', 'pagado')->sum('bonus_amount'),
            'pending_amount' => $bonuses->where('payment_status', 'pendiente')->sum('bonus_amount'),
            'by_type' => $bonuses->groupBy('bonusType.type_name')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('bonus_amount')
                ];
            }),
            'current_year_amount' => $bonuses->where('period_year', now()->year)->sum('bonus_amount'),
            'last_bonus_date' => $bonuses->where('payment_status', 'pagado')->max('payment_date')
        ];
    }

    public function getBonusStatsByType(int $bonusTypeId, int $year = null): array
    {
        $query = $this->model->byType($bonusTypeId);
        
        if ($year) {
            $query->where('period_year', $year);
        }
        
        $bonuses = $query->get();

        return [
            'total_bonuses' => $bonuses->count(),
            'total_amount' => $bonuses->sum('bonus_amount'),
            'paid_count' => $bonuses->where('payment_status', 'pagado')->count(),
            'pending_count' => $bonuses->where('payment_status', 'pendiente')->count(),
            'cancelled_count' => $bonuses->where('payment_status', 'cancelado')->count(),
            'average_amount' => $bonuses->avg('bonus_amount'),
            'by_month' => $bonuses->groupBy('period_month')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('bonus_amount')
                ];
            })
        ];
    }

    public function getTopPerformers(int $month, int $year, int $limit = 10): Collection
    {
        return $this->model->with(['employee.user'])
                          ->byPeriod($month, $year)
                          ->where('payment_status', 'pagado')
                          ->selectRaw('employee_id, SUM(bonus_amount) as total_bonus')
                          ->groupBy('employee_id')
                          ->orderBy('total_bonus', 'desc')
                          ->limit($limit)
                          ->get();
    }

    public function getDuplicateCheck(int $employeeId, int $bonusTypeId, int $month, int $year, int $quarter = null): ?Bonus
    {
        $query = $this->model->where('employee_id', $employeeId)
                            ->where('bonus_type_id', $bonusTypeId);

        if ($quarter) {
            $query->byQuarter($quarter, $year);
        } else {
            $query->byPeriod($month, $year);
        }

        return $query->first();
    }

    public function getBonusesRequiringApproval(array $filters = []): Collection
    {
        $query = $this->model->with([
            'employee.user', 
            'bonusType', 
            'creator.user'
        ])
        ->pendingApproval();

        if (isset($filters['bonus_type_id'])) {
            $query->byType($filters['bonus_type_id']);
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        return $query->orderBy('created_at', 'asc')->get();
    }

    public function getMonthlyBonusTrend(int $year, int $bonusTypeId = null): array
    {
        $query = $this->model->where('period_year', $year)
                            ->where('payment_status', 'pagado');

        if ($bonusTypeId) {
            $query->byType($bonusTypeId);
        }

        $bonuses = $query->get();

        $trend = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthBonuses = $bonuses->where('period_month', $month);
            $trend[$month] = [
                'month' => $month,
                'count' => $monthBonuses->count(),
                'amount' => $monthBonuses->sum('bonus_amount'),
                'employees' => $monthBonuses->unique('employee_id')->count()
            ];
        }

        return $trend;
    }

    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['bonus_type_id'])) {
            $query->byType($filters['bonus_type_id']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['period_month']) && isset($filters['period_year'])) {
            $query->byPeriod($filters['period_month'], $filters['period_year']);
        }

        if (isset($filters['period_quarter']) && isset($filters['period_year'])) {
            $query->byQuarter($filters['period_quarter'], $filters['period_year']);
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (isset($filters['approved_by'])) {
            $query->where('approved_by', $filters['approved_by']);
        }

        if (isset($filters['requires_approval'])) {
            if ($filters['requires_approval']) {
                $query->pendingApproval();
            } else {
                $query->approved();
            }
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['amount_min'])) {
            $query->where('bonus_amount', '>=', $filters['amount_min']);
        }

        if (isset($filters['amount_max'])) {
            $query->where('bonus_amount', '<=', $filters['amount_max']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('bonus_name', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('employee.user', function($userQuery) use ($search) {
                      $userQuery->where('first_name', 'like', "%{$search}%")
                               ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }
    }
}
