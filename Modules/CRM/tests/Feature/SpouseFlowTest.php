<?php

namespace Modules\CRM\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\CRM\Models\Client;
use Modules\Security\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SpouseFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.defaults.guard' => 'sanctum']);
        Permission::create(['name' => 'crm.access', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'crm.clients.update', 'guard_name' => 'sanctum']);
    }

    /** Listar cónyuges de un cliente */
    public function test_list_spouses()
    {
        Sanctum::actingAs(User::factory()->withPermission(['crm.access', 'crm.clients.update'])->create());
        $c1 = Client::factory()->create();
        $c2 = Client::factory()->create();
        $c1->spouses()->attach($c2->client_id);

        $this->getJson("/api/v1/crm/clients/{$c1->client_id}/spouses")
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'full_name', 'doc', 'email', 'phones', 'type', 'addresses', 'interactions', 'created_at']]]);
    }

    /** Agregar un cónyuge */
    public function test_add_spouse()
    {
        Sanctum::actingAs(User::factory()->withPermission([/*'crm.access',*/ 'crm.clients.update'])->create());
        $c1 = Client::factory()->create();
        $c2 = Client::factory()->create();
        
        $this->postJson("/api/v1/crm/clients/{$c1->client_id}/spouses",[ 'partner_id'=>$c2->client_id] )
            ->assertOk()->assertJson(['message' => 'Conyugue agregado correctamente']);
    }

    /** Eliminar un cónyuge */
    /* public function test_remove_spouse()
    {
        Sanctum::actingAs(User::factory()->withPermission(['crm.access', 'crm.clients.update'])->create());
        $c1 = Client::factory()->create();
        $c2 = Client::factory()->create();
        $c1->spouses()->attach($c2->client_id);

        $this->deleteJson("/api/v1/crm/clients/{$c1->client_id}/spouses/{$c2->client_id}")
            ->assertOk()->assertJson(['message' => 'Conyugue eliminado correctamente']);
    }*/

    public function test_remove_spouse()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['crm.access', 'crm.clients.update'])->create()
        );
        $c1 = Client::factory()->create();
        $c2 = Client::factory()->create();
        $c1->spouses()->attach($c2->client_id);

        $this->deleteJson("/api/v1/crm/clients/{$c1->client_id}/spouses/{$c2->client_id}")
            ->assertOk()
            ->assertJsonFragment([
                'message' => 'Conyugue eliminado correctamente',
            ]);
    }
}
