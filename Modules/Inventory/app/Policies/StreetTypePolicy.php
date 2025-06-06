<?php

namespace Modules\Inventory\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Inventory\Models\StreetType;
use Modules\Security\Models\User;

class StreetTypePolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct() {}
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermissionTo('inventory.manzanas.view');
    }

    public function view(User $user, StreetType $streetType)
    {
        return $user->hasPermissionTo('inventory.manzanas.view');
    }

    public function create(User $user)
    {
        return $user->hasPermissionTo('inventory.manzanas.store');
    }

    public function update(User $user, StreetType $streetType)
    {
        return $user->hasPermissionTo('inventory.manzanas.update');
    }

    public function delete(User $user, StreetType $streetType)
    {
        return $user->hasPermissionTo('inventory.manzanas.delete');
    }
}
