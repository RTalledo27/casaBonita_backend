<?php

namespace Modules\Security\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        $user = User::create([
            'username'      => $data['username'],
            'email'         => $data['email'],
            'password_hash' => bcrypt($data['password']),
            'status'        => $data['status'] ?? 'active',
        ]);

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
        if (isset($data['password'])) {
            $data['password_hash'] = bcrypt($data['password']);
        }
        $user->update($data);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

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
