<?php

namespace Modules\Security\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Security\Models\User;

class UserRepository
{

    
    public function handle() {}

    /**
     * Paginate users with roles and optional search.
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return User::with('roles')
            ->when(
                $filters['search'] ?? null,
                fn($q, $search) =>
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email',    'like', "%{$search}%")
            )
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Create a new user and assign roles.
     */
    public function create(array $data): User
    {
        Log::alert('Creating user', [
            'data' => $data,
        ]);
        // 1) Extraigo el UploadedFile y lo guardo en 'users'
        $file = $data['photo_profile'] ?? null;
        unset($data['photo_profile']);

        if ($file instanceof UploadedFile) {
            // $module puede venir del controlador o deducirse, ej: 'security/users'
            $module = 'security/users';
            $path   = $file->store($module, 'public');
            $data['photo_profile'] = $path;
        }

        $data['created_by'] = Auth::id();

        // 2) Creo el usuario
        $user = User::create([
            'username'      => $data['username'],
            'first_name'    => $data['first_name']    ?? null,
            'last_name'     => $data['last_name']     ?? null,
            'dni'           => $data['dni']           ?? null,
            'email'         => $data['email'],
            'phone'         => $data['phone']         ?? null,
            'status'        => $data['status']        ?? 'active',
            'position'      => $data['position']      ?? null,
            'department'    => $data['department']    ?? null,
            'address'       => $data['address']       ?? null,
            'hire_date'     => $data['hire_date']     ?? null,
            'birth_date'    => $data['birth_date']    ?? null,
            'photo_profile' => $data['photo_profile'] ?? null,
            'password_hash' => bcrypt($data['password']),
            'created_by'    => $data['created_by'],
        ]);

        // 3) Sincronizo roles
        if (!empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user->load('roles');
    }


    /**
     * Update existing user.
     */
    public function update(User $user, array $data): User
    {
        Log::alert('Updating user', [
            'user_id' => $user,
            'data'    => $data,
        ]);
        // 1) Si viene foto nueva, guárdala y sustituye el campo
        if (!empty($data['photo_profile']) && $data['photo_profile'] instanceof UploadedFile) {
            // Opcional: puedes organizarla por módulo/carpetas
            $path = $data['photo_profile']->store('security/users', 'public');
            $data['photo_profile'] = $path;
        } else {
            // No quieres sobreescribir con null si no se envía
            unset($data['photo_profile']);
        }

        // 2) Si enviaron contraseña, hasheala
        if (isset($data['password'])) {
            $data['password_hash'] = bcrypt($data['password']);
        }

        // 3) Actualiza el resto de campos masivos
        $user->update($data);

        // 4) Si mandaron roles, sincronízalos
        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        // 5) Devuelve el usuario con sus roles cargados
        return $user->load('roles');
    }



/**
 * Delete a user.
 */
    public function delete(User $user): void
    {
        $user->delete();
    }

    public function allWithRoles()
    {
        return User::with('roles')->paginate(10);
    }
}
