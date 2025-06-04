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

    public function viewAny(User $user)
    {
        return $user->hasPermissionTo('crm.clients.view');
    }

    public function view(User $user, Client $client)
    {
        return $user->hasPermissionTo('crm.clients.view');
    }

    public function create(User $user)
    {
        return $user->hasPermission('crm.clients.create');
    }

    public function update(User $user, Client $client)
    {
        return $user->hasPermission('crm.clients.update');
    }

    public function delete(User $user, Client $client)
    {
        return $user->hasPermission('crm.clients.delete');
    }

    public function export(User $user)
    {
        return $user->hasPermission('crm.clients.export');
    }

    public function summary(User $user, Client $client)
    {
        return $user->hasPermission('crm.clients.summary');
    }

    public function manageSpouses(User $user, Client $client)
    {
        return $user->hasPermission('crm.clients.spouses');
    }
}
