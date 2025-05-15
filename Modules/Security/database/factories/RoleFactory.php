<?php

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

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
        ];
    }
}

