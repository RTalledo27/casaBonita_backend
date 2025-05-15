<?php

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

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
}

