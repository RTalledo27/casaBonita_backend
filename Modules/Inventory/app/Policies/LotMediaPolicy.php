<?php

namespace Modules\Inventory\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Security\Models\User;

class LotMediaPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct() {}

    public function manage(User $user)
    {
        return $user->hasPermissionTo('inventory.media.manage');
    }
}
