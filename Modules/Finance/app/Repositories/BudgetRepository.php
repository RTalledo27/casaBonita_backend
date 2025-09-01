<?php

namespace Modules\Finance\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Finance\Models\Budget;

class BudgetRepository
{
    protected Budget $model;

    public function __construct(Budget $model)
    {
        $this->model = $model;
    }

    public function findAll(array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['budgetLines.account', 'creator', 'approver']);

        if (isset($filters['fiscal_year'])) {
            $query->where('fiscal_year', $filters['fiscal_year']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('created_at', 'desc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    public function findById(int $id): ?Budget
    {
        return $this->model->with(['budgetLines.account', 'creator', 'approver'])
                          ->find($id);
    }

    public function create(array $data): Budget
    {
        return $this->model->create($data);
    }

    public function update(Budget $budget, array $data): Budget
    {
        $budget->update($data);
        return $budget->fresh();
    }

    public function delete(Budget $budget): bool
    {
        return $budget->delete();
    }

    public function findByFiscalYear(int $fiscalYear): Collection
    {
        return $this->model->where('fiscal_year', $fiscalYear)
                          ->with(['budgetLines.account'])
                          ->get();
    }

    public function getBudgetSummary(int $fiscalYear): array
    {
        $budgets = $this->findByFiscalYear($fiscalYear);
        
        return [
            'total_budgeted' => $budgets->sum('total_amount'),
            'total_executed' => $budgets->sum(function ($budget) {
                return $budget->getTotalExecutedAttribute();
            }),
            'execution_percentage' => $budgets->avg(function ($budget) {
                return $budget->getExecutionPercentageAttribute();
            }),
            'budgets_count' => $budgets->count()
        ];
    }}
