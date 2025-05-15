<?php

namespace Modules\CRM\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\CRM\Models\Client;
use Modules\Security\Models\User;

class ClientPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct() {}

    public function viewAny(User $user): bool
    {
        return $user->can('crm.clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        return $user->can('crm.clients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('crm.clients.create');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can('crm.clients.update');
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->can('crm.clients.delete');
    }

    // (Opcional) restaurar y forzar eliminación:
    public function restore(User $user, Client $client): bool
    {
        return $user->can('crm.clients.restore');
    }

    public function forceDelete(User $user, Client $client): bool
    {
        return $user->can('crm.clients.force-delete');
    }
}
