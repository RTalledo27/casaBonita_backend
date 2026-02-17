<?php

namespace Modules\HumanResources\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\HumanResources\Models\Employee;

class EmployeeRepository
{
    public function __construct(protected Employee $model) {}

    public function getAll(array $filters = []): Collection
    {
        $month = $filters['month'] ?? now()->month;
        $year = $filters['year'] ?? now()->year;
        
        $query = $this->model->with([
            'user', 
            'team',
            'position',
            'commissions' => function ($query) use ($month, $year) {
                $query->where('period_month', $month)
                      ->where('period_year', $year);
            },
            'bonuses' => function ($query) use ($month, $year) {
                $query->where('period_month', $month)
                      ->where('period_year', $year);
            }
        ]);

        if (isset($filters['employee_type'])) {
            $query->where('employee_type', $filters['employee_type']);
        }

        if (isset($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        if (isset($filters['employment_status'])) {
            $query->where('employment_status', $filters['employment_status']);
        } else {
            $query->where('employment_status', 'activo');
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator    {
        $query = $this->model->with(['user', 'team', 'position']);

        if (isset($filters['employee_type'])) {
            $query->where('employee_type', $filters['employee_type']);
        }

        if (isset($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        if (isset($filters['employment_status'])) {
            $query->where('employment_status', $filters['employment_status']);
        } else {
            $query->where('employment_status', 'activo');
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('employee_code', 'like', "%{$search}%");
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }



    public function findById(int $id): ?Employee
    {
        return $this->model->with(['user', 'team', 'position', 'commissions', 'bonuses'])
            ->find($id);
    }
    public function create(array $data): Employee
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?Employee
    {
        $employee = $this->findById($id);
        if ($employee) {
            $employee->update($data);
            return $employee->fresh();
        }
        return null;
    }

    public function delete(int $id): bool
    {
        $employee = $this->findById($id);
        if (!$employee) {
            return false;
        }

        return $employee->delete();
    }

    public function getAdvisors(): Collection
    {
        return $this->model->with(['user', 'team', 'position'])->advisors()->get();
    }


    public function getByType(string $type): Collection
    {
        return $this->model->with(['user', 'team'])
            ->where('employee_type', $type)
            ->active()
            ->get();
    }


    

    public function generateEmployeeCode(): string
    {
        $lastEmployee = $this->model->orderBy('employee_id', 'desc')->first();
        $nextNumber = $lastEmployee ? (int)substr($lastEmployee->employee_code, 3) + 1 : 1;
        return 'EMP' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function getTopPerformers(int $month, int $year, int $limit = 10): Collection
    {
        return $this->model->with([
            'user', 
            'commissions' => function ($query) use ($month, $year) {
                $query->byPeriod($month, $year);
            },
            'bonuses' => function ($query) use ($month, $year) {
                $query->where('period_month', $month)
                      ->where('period_year', $year);
            }
        ])
            ->advisors()
            ->active()
            ->get()
            ->sortByDesc(function ($employee) use ($month, $year) {
                $commissions = $employee->commissions->sum('commission_amount');
                $bonuses = $employee->bonuses->sum('bonus_amount');
                return $commissions + $bonuses;
            })
            ->take($limit);
    }

//LUEGO IMPLEMENTARLA
    public function getByTeam(int $teamId): Collection
    {
        return $this->model->where('team_id', $teamId)
            ->active()
            ->with(['user'])
            ->get();
    }

    public function getEmployeesWithSalesData($month, $year): Collection
    {
        return $this->model->with([
            'contracts' => function ($query) use ($month, $year) {
                $query->whereMonth('sign_date', $month)
                    ->whereYear('sign_date', $year);
            },
            'commissions' => function ($query) use ($month, $year) {
                $query->where('period_month', $month)
                    ->where('period_year', $year);
            },
            'bonuses' => function ($query) use ($month, $year) {
                $query->where('period_month', $month)
                    ->where('period_year', $year);
            }
        ])->advisors()->active()->get();
    }

    /**
     * Obtener empleados que no tienen usuario asociado
     */
    public function getEmployeesWithoutUser(): Collection
    {
        return $this->model->whereNull('user_id')
            ->where('employment_status', 'activo')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtener empleados que tienen comisiones para integración HR-Collections
     */
    public function getEmployeesWithCommissions(array $filters = []): Collection
    {
        $query = $this->model->with([
            'user', 
            'team',
            'commissions' => function ($commissionQuery) use ($filters) {
                if (isset($filters['verification_status'])) {
                    $commissionQuery->where('verification_status', $filters['verification_status']);
                }
                if (isset($filters['payment_status'])) {
                    $commissionQuery->where('payment_status', $filters['payment_status']);
                }
                if (isset($filters['period_start'])) {
                    $commissionQuery->where('period_start', '>=', $filters['period_start']);
                }
                if (isset($filters['period_end'])) {
                    $commissionQuery->where('period_end', '<=', $filters['period_end']);
                }
                $commissionQuery->with('customer');
            }
        ])->whereHas('commissions', function ($commissionQuery) use ($filters) {
            if (isset($filters['verification_status'])) {
                $commissionQuery->where('verification_status', $filters['verification_status']);
            }
            if (isset($filters['payment_status'])) {
                $commissionQuery->where('payment_status', $filters['payment_status']);
            }
            if (isset($filters['period_start'])) {
                $commissionQuery->where('period_start', '>=', $filters['period_start']);
            }
            if (isset($filters['period_end'])) {
                $commissionQuery->where('period_end', '<=', $filters['period_end']);
            }
        });

        if (isset($filters['status']) && $filters['status'] === 'active') {
            $query->where('employment_status', 'activo');
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
