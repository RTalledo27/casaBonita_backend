<?php

namespace Modules\Finance\Services;

use Exception;
use Illuminate\Support\Facades\Auth;
use Modules\Finance\Models\Budget;
use Modules\Finance\Repositories\BudgetRepository;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\BudgetLine;

class BudgetService
{
    protected BudgetRepository $budgetRepository;

    public function __construct(BudgetRepository $budgetRepository)
    {
        $this->budgetRepository = $budgetRepository;
    }

    public function getAllBudgets(array $filters = [])
    {
        return $this->budgetRepository->findAll($filters);
    }

    public function getBudgetById(int $id): ?Budget
    {
        return $this->budgetRepository->findById($id);
    }

    public function createBudget(array $data): Budget
    {
        try {
            DB::beginTransaction();

            $budgetData = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'fiscal_year' => $data['fiscal_year'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_amount' => $data['total_amount'],
                'status' => 'draft',
                'created_by' => Auth::id()
            ];

            $budget = $this->budgetRepository->create($budgetData);

            if (isset($data['budget_lines'])) {
                $this->createBudgetLines($budget, $data['budget_lines']);
            }

            Db::commit();
            return $budget->fresh(['budgetLines.account']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateBudget(int $id, array $data): Budget
    {
        try {
            DB::beginTransaction();

            $budget = $this->budgetRepository->findById($id);
            if (!$budget) {
                throw new Exception('Presupuesto no encontrado');
            }

            $budget = $this->budgetRepository->update($budget, $data);

            if (isset($data['budget_lines'])) {
                // Eliminar líneas existentes y crear nuevas
                $budget->budgetLines()->delete();
                $this->createBudgetLines($budget, $data['budget_lines']);
            }

            DB::commit();
            return $budget->fresh(['budgetLines.account']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteBudget(int $id): bool
    {
        $budget = $this->budgetRepository->findById($id);
        if (!$budget) {
            throw new Exception('Presupuesto no encontrado');
        }

        return $this->budgetRepository->delete($budget);
    }

    public function approveBudget(int $id): Budget
    {
        try {
            DB::beginTransaction();

            $budget = $this->budgetRepository->findById($id);
            if (!$budget) {
                throw new Exception('Presupuesto no encontrado');
            }

            $updateData = [
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ];

            $budget = $this->budgetRepository->update($budget, $updateData);

            DB::commit();
            return $budget;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getBudgetSummary(int $fiscalYear): array
    {
        return $this->budgetRepository->getBudgetSummary($fiscalYear);
    }

    protected function createBudgetLines(Budget $budget, array $lines): void
    {
        foreach ($lines as $line) {
            BudgetLine::create([
                'budget_id' => $budget->id,
                'account_id' => $line['account_id'],
                'description' => $line['description'] ?? null,
                'budgeted_amount' => $line['budgeted_amount'],
                'quarter_1' => $line['quarter_1'] ?? 0,
                'quarter_2' => $line['quarter_2'] ?? 0,
                'quarter_3' => $line['quarter_3'] ?? 0,
                'quarter_4' => $line['quarter_4'] ?? 0,
            ]);
        }
    }

    public function updateBudgetExecution(int $budgetId, int $accountId, float $amount): void
    {
        $budgetLine = BudgetLine::where('budget_id', $budgetId)
                                ->where('account_id', $accountId)
                                ->first();

        if ($budgetLine) {
            $budgetLine->increment('executed_amount', $amount);
        }
    }
}
