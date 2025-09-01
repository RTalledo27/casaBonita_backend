<?php

namespace Modules\Sales\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Sales\Models\Reservation;
use Modules\Security\Models\User;

class ReservationPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct() {}



    /**
     * Determine whether the user can view any reservations.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sales.reservations.view');
    }

    /**
     * Determine whether the user can view a specific reservation.
     */
    public function view(User $user, Reservation $reservation): bool
    {
        return $user->hasPermissionTo('sales.reservations.view');
    }

    /**
     * Determine whether the user can create a reservation.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sales.reservations.store');
    }

    /**
     * Determine whether the user can update a reservation.
     */
    public function update(User $user, Reservation $reservation): bool
    {
        return $user->hasPermissionTo('sales.reservations.update');
    }

    /**
     * Determine whether the user can delete a reservation.
     */
    public function delete(User $user, Reservation $reservation): bool
    {
        return $user->hasPermissionTo('sales.reservations.destroy');
    }
}