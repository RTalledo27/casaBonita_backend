<?php

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\User;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Security\Models\User::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'username'      => $this->faker->unique()->userName(),
            'password_hash' => bcrypt('Romaim27'),
            'email'         => $this->faker->unique()->safeEmail(),
            'status'        => 'active',
            'photo_profile' => $this->faker->imageUrl(640, 480, 'people', true),
        ];
    }

    /**
     * State: crea el usuario y le asigna uno o varios permisos.
     */
    public function withPermission(string|array $permissions)
    {
        return $this->afterCreating(function (User $user) use ($permissions) {
            $user->givePermissionTo($permissions);
        });
    }
}

