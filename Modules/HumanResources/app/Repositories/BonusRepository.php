<?php

namespace Modules\HumanResources\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\HumanResources\Models\Bonus;

class BonusRepository
{
    public function __construct(protected Bonus $model) {}

    public function getAll(array $filters = []): Collection
    {
        $query = $this->model->with(['employee.user', 'approver.user']);

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['bonus_type'])) {
            $query->where('bonus_type', $filters['bonus_type']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['period_month']) && isset($filters['period_year'])) {
            $query->where('period_month', $filters['period_month'])
                ->where('period_year', $filters['period_year']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function findById(int $id): ?Bonus
    {
        return $this->model->with(['employee.user', 'approver.user'])->find($id);
    }

    public function create(array $data): Bonus
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?Bonus
    {
        $bonus = $this->findById($id);
        if ($bonus) {
            $bonus->update($data);
            return $bonus->fresh();
        }
        return null;
    }

    public function markAsPaid(int $id): bool
    {
        $bonus = $this->findById($id);
        if ($bonus) {
            return $bonus->update([
                'payment_status' => 'pagado',
                'payment_date' => now()
            ]);
        }
        return false;
    }
}
