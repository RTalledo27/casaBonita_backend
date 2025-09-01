<?php

namespace Modules\Sales\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Sales\Models\Contract;
use Modules\Security\Models\User;

class ContractPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct() {}

    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermissionTo('sales.contracts.view');
    }

    public function view(User $user, Contract $contract)
    {
        return $user->hasPermissionTo('sales.contracts.view');
    }

    public function create(User $user)
    {
        return $user->hasPermissionTo('sales.contracts.store');
    }

    public function update(User $user, Contract $contract)
    {
        return $user->hasPermissionTo('sales.contracts.update');
    }

    public function delete(User $user, Contract $contract)
    {
        return $user->hasPermissionTo('sales.contracts.delete');
    }
}

