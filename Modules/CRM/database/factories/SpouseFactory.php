<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Client;

class SpouseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\CRM\Models\Spouse::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $clientA = Client::inRandomOrder()->first() ?? Client::factory()->create();
        // evita emparejarse consigo mismo
        $clientB = Client::where('client_id', '!=', $clientA->client_id)->inRandomOrder()->first()
            ?? Client::factory()->create();

        return [
            'client_id'  => $clientA->client_id,
            'partner_id' => $clientB->client_id,
        ];
    }
}

