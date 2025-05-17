<?php

namespace Modules\CRM\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\CRM\Models\Client;
use Modules\CRM\Models\CrmInteraction;
use Modules\Security\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CrmInteractionFlowTest extends TestCase
{
    /**
     * A basic test example.
     */


    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.defaults.guard' => 'sanctum']);

        foreach (
            [
                'crm.access',
                'crm.interactions.view',
                'crm.interactions.create',
                'crm.interactions.update',
                'crm.interactions.delete',
            ] as $perm
        ) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }
    }

    /** @test */
    public function can_list_interactions()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['crm.access', 'crm.interactions.view'])->create()
        );

        $client = Client::factory()->create();
        CrmInteraction::factory()->count(2)->create(['client_id' => $client->client_id]);

        $this->getJson('/api/v1/crm/interactions')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'client_id', 'channel', 'notes']]]);
    }

    /** @test */
    public function can_create_interaction()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['crm.access', 'crm.interactions.create'])->create()
        );

        $client = Client::factory()->create();

        //dd($client);

        $payload = [
            'client_id' => $client->client_id,
            'channel' => 'email',
            'notes' => 'Se contactó para cotización',
            'date' => now()->format('Y-m-d H:i:s'),
        ];

        $this->postJson('/api/v1/crm/interactions', $payload)
            ->assertCreated()
            ->assertJsonFragment(['channel' => 'email']);
    }

    /** @test */
    public function can_update_interaction()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['crm.access', 'crm.interactions.update'])->create()
        );

        $interaction = CrmInteraction::factory()->create();

        $this->putJson("/api/v1/crm/interactions/{$interaction->interaction_id}", [
            'client_id' => $interaction->client_id,
            'channel' => 'visit',
            'notes' => 'Cambió canal',
            'date' => now()->format('Y-m-d H:i:s'),
        ])->assertOk()->assertJsonFragment(['channel' => 'visit']);
    }

    /** @test */
    public function can_delete_interaction()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['crm.access', 'crm.interactions.delete'])->create()
        );

        $interaction = CrmInteraction::factory()->create();
        
        $this->deleteJson("/api/v1/crm/interactions/{$interaction->interaction_id}")
            ->assertNoContent();
    }

}
