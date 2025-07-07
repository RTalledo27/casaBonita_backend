<?php

namespace Modules\Inventory\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Inventory\Models\Lot;
use Modules\Security\Models\User;

class LotPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct() {}


    public function viewAny(User $user)
    {
        return $user->hasPermissionTo('inventory.lots.view');
    }

    public function view(User $user, Lot $lot)
    {
        return $user->hasPermissionTo('inventory.lots.view');
    }

    public function create(User $user)
    {
        return $user->hasPermissionTo('inventory.lots.store');
    }

    public function update(User $user, Lot $lot)
    {
        return $user->hasPermissionTo('inventory.lots.update');
    }

    public function delete(User $user, Lot $lot)
    {
        return $user->hasPermissionTo('inventory.lots.delete');
    }
}
