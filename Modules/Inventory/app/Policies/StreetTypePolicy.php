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

    public function viewAny(User $user)
    {
        return $user->hasPermissionTo('inventory.street-types.view');
    }

    public function view(User $user, StreetType $streetType)
    {
        return $user->hasPermissionTo('inventory.street-types.view');
    }

    public function create(User $user)
    {
        return $user->hasPermissionTo('inventory.street-types.store');
    }

    public function update(User $user, StreetType $streetType)
    {
        return $user->hasPermissionTo('inventory.street-types.update');
    }

    public function delete(User $user, StreetType $streetType)
    {
        return $user->hasPermissionTo('inventory.street-types.delete');
    }
}
