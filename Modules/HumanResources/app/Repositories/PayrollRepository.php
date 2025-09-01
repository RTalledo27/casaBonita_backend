<?php

namespace Modules\HumanResources\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\HumanResources\Models\Payroll;

class PayrollRepository
{
    public function __construct(protected Payroll $model) {}

    public function getAll(array $filters = []): Collection
    {
        $query = $this->model->with(['employee.user', 'processedByEmployee.user', 'approvedByEmployee.user']);

        if (isset($filters['period'])) {
            $query->byPeriod($filters['period']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }


    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['employee.user', 'processedByEmployee.user', 'approvedByEmployee.user']);

        if (isset($filters['period'])) {
            $query->byPeriod($filters['period']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['search'])) {
            $query->whereHas('employee.user', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            })->orWhereHas('employee', function ($q) use ($filters) {
                $q->where('employee_code', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getGlobalTotals(array $filters = []): array
    {
        $query = $this->model->query();

        if (isset($filters['period'])) {
            $query->byPeriod($filters['period']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['search'])) {
            $query->whereHas('employee.user', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            })->orWhereHas('employee', function ($q) use ($filters) {
                $q->where('employee_code', 'like', '%' . $filters['search'] . '%');
            });
        }

        $totals = $query->selectRaw('
            SUM(gross_salary) as total_gross,
            SUM(net_salary) as total_net,
            SUM(total_deductions) as total_deductions,
            COUNT(*) as total_records
        ')->first();

        return [
            'total_gross' => (float) ($totals->total_gross ?? 0),
            'total_net' => (float) ($totals->total_net ?? 0),
            'total_deductions' => (float) ($totals->total_deductions ?? 0),
            'total_records' => (int) ($totals->total_records ?? 0)
        ];
    }

    public function findById(int $id): ?Payroll
    {
        return $this->model->with(['employee.user', 'processedByEmployee.user', 'approvedByEmployee.user'])
            ->find($id);
    }

    public function create(array $data): Payroll
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?Payroll
    {
        $payroll = $this->findById($id);
        if (!$payroll) {
            return null;
        }

        $payroll->update($data);
        return $payroll->fresh(['employee.user', 'processedByEmployee.user', 'approvedByEmployee.user']);
    }

    public function delete(int $id): bool
    {
        $payroll = $this->findById($id);
        return $payroll ? $payroll->delete() : false;
    }

    public function findByEmployeeAndPeriod(int $employeeId, string $period): ?Payroll
    {
        return $this->model->where('employee_id', $employeeId)
            ->where('payroll_period', $period)
            ->first();
    }

    public function getByEmployeeAndPeriod(int $employeeId, string $period): ?Payroll
    {
        return $this->model->where('employee_id', $employeeId)
            ->where('payroll_period', $period)
            ->first();
    }


    public function getByStatus(string $status): Collection
    {
        return $this->model->with(['employee.user'])
            ->where('status', $status)
            ->get();
    }

    public function getForPeriod(string $period): Collection
    {
        return $this->model->with(['employee.user'])
            ->byPeriod($period)
            ->get();
    }
}
