<?php

namespace Modules\Collections\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Collections\Models\AccountReceivable;
use Modules\Security\Models\User;

class AccountReceivablePolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    use HandlesAuthorization;

    /**
     * Ver cualquier cuenta por cobrar
     */
    public function viewAny(User $user)
    {
        return $user->hasPermissionTo('collections.receivables.view');
    }

    /**
     * Ver cuenta por cobrar específica
     */
    public function view(User $user, AccountReceivable $accountReceivable)
    {
        // Puede ver si tiene permisos generales o si es el cobrador asignado
        return $user->hasPermissionTo('collections.receivables.view') ||
            $accountReceivable->assigned_collector_id === $user->user_id;
    }

    /**
     * Crear nueva cuenta por cobrar
     */
    public function create(User $user)
    {
        return $user->hasPermissionTo('collections.receivables.create');
    }

    /**
     * Actualizar cuenta por cobrar
     */
    public function update(User $user, AccountReceivable $accountReceivable)
    {
        // No se puede editar si está pagada o cancelada
        if (in_array($accountReceivable->status, ['PAID', 'CANCELLED'])) {
            return false;
        }

        return $user->hasPermissionTo('collections.receivables.edit');
    }

    /**
     * Eliminar cuenta por cobrar
     */
    public function delete(User $user, AccountReceivable $accountReceivable)
    {
        // No se puede eliminar si tiene pagos aplicados
        if ($accountReceivable->payments()->exists()) {
            return false;
        }

        return $user->hasPermissionTo('collections.receivables.delete');
    }

    /**
     * Registrar pago
     */
    public function recordPayment(User $user, AccountReceivable $accountReceivable)
    {
        // No se puede registrar pago si está pagada o cancelada
        if (in_array($accountReceivable->status, ['PAID', 'CANCELLED'])) {
            return false;
        }

        return $user->hasPermissionTo('collections.payments.create') ||
            $accountReceivable->assigned_collector_id === $user->user_id;
    }

    /**
     * Asignar cobrador
     */
    public function assignCollector(User $user, AccountReceivable $accountReceivable)
    {
        return $user->hasPermissionTo('collections.receivables.assign_collector');
    }

    /**
     * Ver reportes
     */
    public function viewReports(User $user)
    {
        return $user->hasPermissionTo('collections.reports.view');
    }

    /**
     * Generar alertas
     */
    public function generateAlerts(User $user)
    {
        return $user->hasPermissionTo('collections.alerts.view');
    }

    /**
     * Cancelar cuenta por cobrar
     */
    public function cancel(User $user, AccountReceivable $accountReceivable)
    {
        // No se puede cancelar si tiene pagos aplicados
        if ($accountReceivable->payments()->exists()) {
            return false;
        }

        return $user->hasPermissionTo('collections.receivables.cancel');
    }
}
