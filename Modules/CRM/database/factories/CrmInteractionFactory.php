<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Client;
use Modules\Security\Models\User;

class CrmInteractionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\CRM\Models\CrmInteraction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::inRandomOrder()->value('client_id') ?? Client::factory(),
            'user_id'   => User::inRandomOrder()->value('user_id') ?? User::factory(),
            'date'      => $this->faker->dateTimeBetween('-30 days', 'now'),
            'channel'   => $this->faker->randomElement(['call', 'email', 'whatsapp', 'visit', 'other']),
            'notes'     => $this->faker->optional()->sentence(),
        ];
    }
}

