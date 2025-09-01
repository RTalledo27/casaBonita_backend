<?php

namespace Modules\CRM\Tests\Feature;

use Modules\CRM\Repositories\ClientRepository;
use Modules\CRM\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRepositoryTest extends TestCase
{
    /**
     * A basic test example.
     */
    use RefreshDatabase;

    public function test_paginate_and_create_and_update_and_delete()
    {
        $repo = $this->app->make(ClientRepository::class);

        // create
        $client = $repo->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'doc_type' => 'DNI',
            'doc_number' => '123',
            'type' => 'lead',
        ]);
        $this->assertDatabaseHas('clients', ['doc_number' => '123']);

        // paginate
        $pag = $repo->paginate(['per_page' => 1]);
        $this->assertCount(1, $pag->items());

        // update
        $updated = $repo->update($client, ['first_name' => 'Jane']);
        $this->assertEquals('Jane', $updated->first_name);

        // delete
        $repo->delete($client);
        $this->assertDatabaseMissing('clients', ['client_id' => $client->client_id]);
    }
}
