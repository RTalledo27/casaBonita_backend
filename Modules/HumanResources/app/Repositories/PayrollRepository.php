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

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
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
