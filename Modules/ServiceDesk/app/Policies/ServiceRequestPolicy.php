<?php

namespace Modules\ServiceDesk\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Security\Models\User;
use Modules\ServiceDesk\Models\ServiceRequest;

class ServiceRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     * Admins get full access to everything.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Administrador') || $user->hasRole('admin')) {
            return true;
        }
        
        return null; // Fall through to specific policy methods
    }

    /**
     * Can view the list of tickets
     */
    public function viewAny(User $user): bool
    {
        return $user->can('service-desk.tickets.view');
    }

    /**
     * Can view a specific ticket
     */
    public function view(User $user, ServiceRequest $ticket): bool
    {
        // Can view if has permission AND is related to the ticket
        return $user->can('service-desk.tickets.view')
            && ($user->user_id === $ticket->opened_by
                || $user->user_id === $ticket->assigned_to);
    }

    /**
     * Can create tickets
     */
    public function create(User $user): bool
    {
        return $user->can('service-desk.tickets.create') 
            || $user->can('service-desk.tickets.store');
    }

    /**
     * Can update a ticket
     */
    public function update(User $user, ServiceRequest $ticket): bool
    {
        return ($user->can('service-desk.tickets.edit') || $user->can('service-desk.tickets.update'))
            && ($user->user_id === $ticket->opened_by
                || $user->user_id === $ticket->assigned_to);
    }

    /**
     * Can delete a ticket
     */
    public function delete(User $user, ServiceRequest $ticket): bool
    {
        return ($user->can('service-desk.tickets.delete') || $user->can('service-desk.tickets.destroy'))
            && $user->user_id === $ticket->opened_by;
    }

    /**
     * Can assign technician to ticket
     */
    public function assign(User $user, ServiceRequest $ticket): bool
    {
        return $user->can('service-desk.tickets.assign');
    }

    /**
     * Can add actions/comments to ticket
     */
    public function addAction(User $user, ServiceRequest $ticket): bool
    {
        return $user->can('service-desk.tickets.actions')
            || $user->can('service-desk.tickets.comment');
    }

    /**
     * Can change ticket status
     */
    public function changeStatus(User $user, ServiceRequest $ticket): bool
    {
        return $user->can('service-desk.tickets.edit') 
            || $user->can('service-desk.tickets.update')
            || $user->user_id === $ticket->assigned_to;
    }

    /**
     * Can escalate ticket
     */
    public function escalate(User $user, ServiceRequest $ticket): bool
    {
        return $user->can('service-desk.tickets.escalate');
    }

    /**
     * Can close ticket
     */
    public function close(User $user, ServiceRequest $ticket): bool
    {
        return $user->can('service-desk.tickets.close')
            || $user->user_id === $ticket->assigned_to;
    }

    /**
     * Can restore ticket (admin only via before())
     */
    public function restore(User $user, ServiceRequest $ticket): bool
    {
        return false;
    }

    /**
     * Can force delete (admin only via before())
     */
    public function forceDelete(User $user, ServiceRequest $ticket): bool
    {
        return false;
    }
}
