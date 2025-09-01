<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Client;

class FamilyMemberFactory extends Factory
{
    protected $model = \Modules\CRM\Models\FamilyMember::class;

    public function definition(): array
    {
        $client = Client::inRandomOrder()->first() ?? Client::factory()->create();
        return [
            'client_id'  => $client->client_id,
            'first_name' => $this->faker->firstName(),
            'last_name'  => $this->faker->lastName(),
            'dni'        => $this->faker->unique()->numerify('########'),
            'relation'   => $this->faker->randomElement(['spouse', 'child', 'parent', 'other']),
        ];
    }
}

