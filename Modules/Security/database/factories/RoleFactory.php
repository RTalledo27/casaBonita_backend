<?php

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\Role;

class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Security\Models\Role::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name'=> $this->faker->unique()->jobTitle(),
            'description'=> $this->faker->sentence(),
            'guard_name'=> 'sanctum',
        ];
    }

    /**
     * State: crea el usuario y le asigna uno o varios permisos.
     */
    public function withPermission(string|array $permissions)
    {
        return $this->afterCreating(function (Role $role) use ($permissions) {
            $role->givePermissionTo($permissions);
        });
    }
}

