<?php

namespace Modules\Inventory\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Inventory\Models\Manzana;
use Modules\Security\Models\User;

class ManzanaPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct() {}

    public function viewAny(User $user)
    {
        return $user->hasPermissionTo('inventory.manzanas.view');
    }

    public function view(User $user, Manzana $manzana)
    {
        return $user->hasPermissionTo('inventory.manzanas.view');
    }

    public function create(User $user)
    {
        return $user->hasPermissionTo('inventory.manzanas.store');
    }

    public function update(User $user, Manzana $manzana)
    {
        return $user->hasPermissionTo('inventory.manzanas.update');
    }

    public function delete(User $user, Manzana $manzana)
    {
        return $user->hasPermissionTo('inventory.manzanas.delete');
    }
}
