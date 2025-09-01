<?php

namespace Modules\Security\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Security\Models\Role;
use Modules\Security\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RoleFlowTest extends TestCase
{

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.defaults.guard' => 'sanctum']);

        foreach (
            [
                'security.roles.view',
                'security.roles.create',
                'security.roles.update',
                'security.roles.delete',
                'security.permissions.view'
            ] as $perm
        ) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }
    }

    /** @test */
    public function can_list_roles()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['security.roles.view'])->create()
        );

        Role::factory()->count(2)->create();

        

        $this->getJson('/api/v1/security/roles')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    /** @test */
    public function can_create_role()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['security.roles.create'])->create()
        );

        $payload = ['name' => 'new_role'];

        $this->postJson('/api/v1/security/roles', $payload)
            ->assertCreated()
            ->assertJsonFragment(['name' => 'new_role']);
    }

    /** @test */
    public function can_assign_permissions_to_role()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['security.roles.update'])->create()
        );

        $role = Role::factory()->create();
        $perm = Permission::firstOrCreate(['name' => 'security.permissions.view', 'guard_name' => 'sanctum']);


        $payload = ['permissions' => [$perm->name]];

        $this->postJson("/api/v1/security/roles/{$role->role_id}/permissions", $payload)
            ->assertOk()
            ->assertJsonFragment(['permissions' => ['security.permissions.view']]);
    }

    /** @test */
    public function can_show_role()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['security.roles.view'])->create()
        );

        $role = Role::factory()->create();

        $this->getJson("/api/v1/security/roles/{$role->role_id}")
            ->assertOk()
            ->assertJsonFragment(['name' => $role->name]);
    }
}

