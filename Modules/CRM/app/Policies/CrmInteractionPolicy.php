<?php

namespace Modules\CRM\Policies;

use Modules\CRM\Models\CrmInteraction;
use Modules\Security\Models\User;

class CrmInteractionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.interactions.view');
    }

    public function view(User $user, CrmInteraction $interaction): bool
    {
        return $user->can('crm.interactions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('crm.interactions.create');
    }

    public function update(User $user, CrmInteraction $interaction): bool
    {
        return $user->can('crm.interactions.update');
    }

    public function delete(User $user, CrmInteraction $interaction): bool
    {
        return $user->can('crm.interactions.delete');
    }
}
