<?php

namespace Modules\HumanResources\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\HumanResources\Models\Commission;

class CommissionRepository
{
    public function __construct(protected Commission $model) {}

    

   


    public function markAsPaid(int $id): bool
    {
        $commission = $this->findById($id);
        if ($commission) {
            return $commission->update([
                'payment_status' => 'pagado',
                'payment_date' => now()
            ]);
        }
        return false;
    }

    public function getEmployeeCommissionSummary(int $employeeId, int $month, int $year): array
    {
        $commissions = $this->model->where('employee_id', $employeeId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->get();

        return [
            'total_commissions' => $commissions->sum('commission_amount'),
            'paid_commissions' => $commissions->where('payment_status', 'pagado')->sum('commission_amount'),
            'pending_commissions' => $commissions->where('payment_status', 'pendiente')->sum('commission_amount'),
            'commissions_count' => $commissions->count()
        ];
    }


    public function getAll(array $filters = []): Collection
    {
        $query = $this->model->with(['employee.user', 'contract']);

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['period_month'])) {
            $query->where('period_month', $filters['period_month']);
        }

        if (isset($filters['period_year'])) {
            $query->where('period_year', $filters['period_year']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['employee.user', 'contract']);

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['period_month'])) {
            $query->where('period_month', $filters['period_month']);
        }

        if (isset($filters['period_year'])) {
            $query->where('period_year', $filters['period_year']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function findById(int $id): ?Commission
    {
        return $this->model->with(['employee.user', 'contract'])->find($id);
    }

    public function create(array $data): Commission
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?Commission
    {
        $commission = $this->findById($id);
        if (!$commission) {
            return null;
        }

        $commission->update($data);
        return $commission->fresh(['employee.user', 'contract']);
    }

    public function delete(int $id): bool
    {
        $commission = $this->findById($id);
        if (!$commission) {
            return false;
        }

        return $commission->delete();
    }

    public function getPendingForPeriod(int $month, int $year): Collection
    {
        return $this->model->with(['employee.user', 'contract'])
            ->pending()
            ->byPeriod($month, $year)
            ->get();
    }


    

    public function getByEmployee(int $employeeId, int $month = null, int $year = null): Collection
    {
        $query = $this->model->with(['contract'])
            ->byEmployee($employeeId);

        if ($month && $year) {
            $query->byPeriod($month, $year);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function markMultipleAsPaid(array $commissionIds): int
    {
        return $this->model->whereIn('commission_id', $commissionIds)
            ->update([
                'payment_status' => 'paid',
                'payment_date' => now()->toDateString()
            ]);
    }

    public function getTotalCommissionsForPeriod(int $month, int $year): float
    {
        return $this->model->byPeriod($month, $year)
            ->sum('commission_amount');
    }
}
