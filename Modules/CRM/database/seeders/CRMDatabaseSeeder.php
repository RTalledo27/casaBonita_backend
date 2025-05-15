<?php

namespace Modules\CRM\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\CRM\Models\Address;
use Modules\CRM\Models\Client;
use Modules\CRM\Models\CrmInteraction;
use Modules\CRM\Models\Spouse;

class CRMDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // crea 30 clientes, cada uno con 1-3 direcciones
        Client::factory()
            ->count(30)
            ->has(Address::factory()->count(rand(1, 3)), 'addresses')
            ->create();

        // interacciones: 3-6 por cliente
        Client::all()->each(
            fn($client) =>
            CrmInteraction::factory()->count(rand(3, 6))->create(['client_id' => $client->client_id])
        );

        // genera 10 parejas al azar
        Spouse::factory()->count(10)->create();
    }
}
