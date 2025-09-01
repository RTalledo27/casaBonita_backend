<?php

namespace Modules\Finance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Finance\Models\Budget;
use Modules\Security\Models\User;

class BudgetPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('budget.view');
    }

    public function view(User $user, Budget $budget): bool
    {
        return $user->hasPermissionTo('budget.view') ||
            $budget->created_by === $user->id;
    }

    public function store(User $user): bool
    {
        return $user->hasPermissionTo('budget.store');
    }

    public function update(User $user, Budget $budget): bool
    {
        if ($budget->status === 'approved') {
            return $user->hasPermissionTo('budget.update.approved');
        }

        return $user->hasPermissionTo('budget.update') ||
            $budget->created_by === $user->id;
    }

    public function delete(User $user, Budget $budget): bool
    {
        if ($budget->status === 'approved') {
            return false; // No se pueden eliminar presupuestos aprobados
        }

        return $user->hasPermissionTo('budget.delete') ||
            $budget->created_by === $user->id;
    }

    public function approve(User $user, Budget $budget): bool
    {
        return $user->hasPermissionTo('budget.approve') &&
            $budget->status === 'draft' &&
            $budget->created_by !== $user->id; // No puede aprobar su propio presupuesto
    }
}
