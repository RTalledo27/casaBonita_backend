<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\CRM\Models\Client::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'first_name'     => $this->faker->firstName(),
            'last_name'      => $this->faker->lastName(),
            'doc_type'       => $this->faker->randomElement(['DNI', 'CE', 'RUC', 'PAS']),
            'doc_number'     => $this->faker->unique()->numerify('########'),
            'email'          => $this->faker->unique()->safeEmail(),
            'primary_phone'  => $this->faker->numerify('9########'),
            'secondary_phone' => $this->faker->optional()->numerify('9########'),
            'marital_status' => $this->faker->randomElement(['soltero', 'casado', 'divorciado', 'viudo']),
            'type'           => $this->faker->randomElement(['lead', 'client', 'provider']),
            'date'           => $this->faker->date(),
            'occupation'     => $this->faker->jobTitle(),
            'salary'         => $this->faker->optional()->randomFloat(2, 1200, 8000),
            'family_group'   => $this->faker->optional()->word(),
        ];
    }
}

