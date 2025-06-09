<?php

namespace Modules\Sales\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Sales\Models\ContractApproval;
use Modules\Security\Models\User;

class ContractApprovalPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct() {}

    /**
     * Un usuario puede ver un registro de aprobación
     * si es el aprobador asignado o tiene permiso global.
     */
    public function view(User $user, ContractApproval $approval): bool
    {
        return $user->can('sales.approvals.view')
            || $user->user_id === $approval->user_id;
    }

    /** Solo el aprobador asignado puede aprobar. */
    public function approve(User $user, ContractApproval $approval): bool
    {
        return $user->user_id === $approval->user_id
            && $approval->status === 'pendiente';
    }

    /** Solo el aprobador asignado puede rechazar. */
    public function reject(User $user, ContractApproval $approval): bool
    {
        return $user->user_id === $approval->user_id
            && $approval->status === 'pendiente';
    }

    /** Nadie puede eliminar registros de aprobación (ejemplo). */
    public function delete(User $user, ContractApproval $approval): bool
    {
        return false;
    }
}
