<?php
namespace Modules\CRM\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Modules\Security\Models\User;
use Modules\CRM\Models\Client;

class ClientFlowTest extends TestCase
{
    use RefreshDatabase;


    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.defaults.guard' => 'sanctum']);

        foreach (
            [
                'crm.access',
                'crm.clients.view',
                'crm.clients.create',
                'crm.clients.update',
                'crm.clients.delete',
            ] as $perm
        ) {
            Permission::create(['name' => $perm, 'guard_name' => 'sanctum']);
        }
    }

    /** Listado paginado de clientes */
    public function test_list_clients_with_permission()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['crm.access', 'crm.clients.view'])->create()
        );
        Client::factory()->count(3)->create();

        $this->getJson('/api/v1/crm/clients')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'full_name', 'doc', 'email', 'phones', 'type', 'addresses', 'interactions', 'created_at']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    /** No puede listar si no tiene permiso */
    public function test_cannot_list_clients_without_permission()
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/crm/clients')->assertForbidden();
    }

    /** Mostrar un cliente concreto */
    public function test_show_client()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['crm.access', 'crm.clients.view'])->create()
        );
        $client = Client::factory()->create();
        $this->getJson("/api/v1/crm/clients/{$client->client_id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'full_name', 'doc', 'email', 'phones', 'type', 'addresses', 'interactions', 'created_at']
            ]);
    }

    /** Crear un cliente */
    public function test_create_client()
    {
        Sanctum::actingAs(
            User::factory()->withPermission([/*'crm.access',*/ 'crm.clients.create'])->create()
        );
        $payload = Client::factory()->make()->toArray();

        $this->postJson('/api/v1/crm/clients', $payload)
            ->assertCreated()
            ->assertJsonFragment(['full_name' => "{$payload['first_name']} {$payload['last_name']}"]);
    }

    /** Actualizar un cliente */
    public function test_update_client()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['crm.access', 'crm.clients.update'])->create()
        );
        $client = Client::factory()->create();

        $this->putJson("/api/v1/crm/clients/{$client->client_id}", [
            'first_name' => 'NuevoNombre'
        ])
            ->assertOk()
            ->assertJsonFragment(['full_name' => 'NuevoNombre ' . $client->last_name]);
    }

    /** Eliminar un cliente */
    public function test_delete_client()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['crm.access', 'crm.clients.delete'])->create()
        );
        $client = Client::factory()->create();
        $this->deleteJson("/api/v1/crm/clients/{$client->client_id}")->assertNoContent();
    }
}
