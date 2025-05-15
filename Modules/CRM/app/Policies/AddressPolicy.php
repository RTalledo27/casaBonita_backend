<?php

namespace Modules\CRM\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\CRM\Models\Address;
use Modules\Security\Models\User;

class AddressPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct() {}

    public function viewAny(User $user): bool
    {
        return $user->can('crm.addresses.view');
    }

    public function view(User $user, Address $address): bool
    {
        return $user->can('crm.addresses.view');
    }

    public function create(User $user): bool
    {
        return $user->can('crm.addresses.create');
    }

    public function update(User $user, Address $address): bool
    {
        return $user->can('crm.addresses.update');
    }

    public function delete(User $user, Address $address): bool
    {
        return $user->can('crm.addresses.delete');
    }
}
