<?php

namespace Modules\CRM\Policies;

use Modules\CRM\Models\CrmInteraction;
use Modules\Security\Models\User;

class CrmInteractionPolicy
{
    public function viewAny(User $user)
    {
        return $user->hasPermission('crm.interactions.view');
    }

    public function view(User $user, CrmInteraction $interaction)
    {
        return $user->hasPermission('crm.interactions.view');
    }

    public function create(User $user)
    {
        return $user->hasPermission('crm.interactions.create');
    }

    public function update(User $user, CrmInteraction $interaction)
    {
        return $user->hasPermission('crm.interactions.update');
    }

    public function delete(User $user, CrmInteraction $interaction)
    {
        return $user->hasPermission('crm.interactions.delete');
    }
}