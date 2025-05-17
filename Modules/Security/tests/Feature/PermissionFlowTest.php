<?php

namespace Modules\Security\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Security\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.defaults.guard' => 'sanctum']);

        Permission::create(['name' => 'security.permissions.view', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'security.permissions.store', 'guard_name' => 'sanctum']);
    }

    /** @test */
    public function can_list_permissions()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['security.permissions.view'])->create()
        );

        $response = $this->getJson('/api/v1/security/permissions');
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    /** @test */
    public function can_show_permission()
    {
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'security.permissions.view',
            'guard_name' => 'sanctum'
        ]);

        Sanctum::actingAs(
            User::factory()->withPermission(['security.permissions.view'])->create()
        );

        $response = $this->getJson("/api/v1/security/permissions/{$permission->id}");
        $response->assertOk()
        
            ->assertJsonFragment(['name' => 'security.permissions.view']);
    }

    /** @test */
    public function can_create_permission()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['security.permissions.store'])->create()
        );

        $payload = ['name' => 'security.example.permission'];

        $response = $this->postJson('/api/v1/security/permissions', $payload);
        $response->assertCreated()
            ->assertJsonFragment(['name' => 'security.example.permission']);
    }
}
