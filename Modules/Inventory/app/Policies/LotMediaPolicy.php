<?php

namespace Modules\Inventory\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Inventory\Models\LotMedia;
use Modules\Security\Models\User;

class LotMediaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can list / browse media.
     */
    public function viewAny(User $user): bool
    {
        // Permiso: ver todos los archivos de lote
        return $user->hasPermissionTo('inventory.media.index');
    }

    /**
     * Determine whether the user can view a single media record.
     */
    public function view(User $user, LotMedia $media): bool
    {
        return $user->hasPermissionTo('inventory.media.index');
    }

    /**
     * Determine whether the user can create / subir un archivo.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.media.store');
    }

    /**
     * Determine whether the user can update a media record (cambiar tipo, posición, etc.).
     */
    public function update(User $user, LotMedia $media): bool
    {
        return $user->hasPermissionTo('inventory.media.update');
    }

    /**
     * Determine whether the user can delete a media record.
     */
    public function delete(User $user, LotMedia $media): bool
    {
        return $user->hasPermissionTo('inventory.media.destroy');
    }
}
