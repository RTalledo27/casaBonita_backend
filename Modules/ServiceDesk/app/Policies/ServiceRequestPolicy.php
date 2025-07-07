<?php

namespace Modules\ServiceDesk\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Security\Models\User;
use Modules\ServiceDesk\Models\ServiceRequest;

class ServiceRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    use HandlesAuthorization;

    // Puede ver la lista de tickets
    public function viewAny(User $user)
    {
        return $user->can('service-desk.tickets.view');
    }

    public function view(User $user, ServiceRequest $ticket)
    {
        return $user->can('service-desk.tickets.view')
            && ($user->user_id === $ticket->opened_by
                || $user->hasRole('admin')
                || $user->user_id === $ticket->assigned_to);
    }

    public function create(User $user)
    {
        return $user->can('service-desk.tickets.store');
    }

    public function update(User $user, ServiceRequest $ticket)
    {
        return $user->can('service-desk.tickets.update')
            && ($user->user_id === $ticket->opened_by
                || $user->hasRole('admin')
                || $user->user_id === $ticket->assigned_to);
    }

    public function delete(User $user, ServiceRequest $ticket)
    {
        return $user->can('service-desk.tickets.delete')
            && ($user->hasRole('admin') || $user->user_id === $ticket->opened_by);
    }

    public function assign(User $user, ServiceRequest $ticket)
    {
        return $user->can('service-desk.tickets.assign');
    }

    public function addAction(User $user, ServiceRequest $ticket)
    {
        return $user->can('service-desk.tickets.actions')
            && ($user->user_id === $ticket->opened_by
                || $user->hasRole('admin')
                || $user->user_id === $ticket->assigned_to);
    }

    public function close(User $user, ServiceRequest $ticket)
    {
        return $user->can('service-desk.tickets.close');
    }

    public function restore(User $user, ServiceRequest $ticket)
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, ServiceRequest $ticket)
    {
        return $user->hasRole('admin');
    }
}
