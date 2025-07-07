<?php

namespace Modules\ServiceDesk\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Security\Models\User;
use Modules\ServiceDesk\Models\ServiceAction;

class ServiceActionPolicy
{
    use HandlesAuthorization;

    // Puede ver la lista de acciones de cualquier ticket
    public function viewAny(User $user)
    {
        return $user->status === 'active';
    }

    // Puede ver una acción específica
    public function view(User $user, ServiceAction $action)
    {
        return $user->user_id === $action->user_id
            || $user->hasRole('admin')
            // Si quieres, puedes dejar que vea si es creador del ticket
            // || $user->user_id === $action->ticket->opened_by
        ;
    }

    // Puede crear una acción (comentario, cambio de estado)
    public function create(User $user)
    {
        return $user->status === 'active';
    }

    // Puede actualizar una acción (poco común, usualmente solo admin)
    public function update(User $user, ServiceAction $action)
    {
        return $user->user_id === $action->user_id
            || $user->hasRole('admin');
    }

    // Puede eliminar una acción (poco común, usualmente solo admin)
    public function delete(User $user, ServiceAction $action)
    {
        return $user->hasRole('admin');
    }

    // Puede restaurar una acción borrada
    public function restore(User $user, ServiceAction $action)
    {
        return $user->hasRole('admin');
    }

    // Puede forzar borrado definitivo
    public function forceDelete(User $user, ServiceAction $action)
    {
        return $user->hasRole('admin');
    }
}
