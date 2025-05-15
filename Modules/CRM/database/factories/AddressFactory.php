<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\CRM\Models\Address::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'line1'   => $this->faker->streetAddress(),
            'line2'   => $this->faker->optional()->secondaryAddress(),
            'city'    => $this->faker->city(),
            'state'   => $this->faker->optional()->state(),
            'country' => $this->faker->country(),
            'zip_code' => $this->faker->postcode(),
        ];
    }
}

