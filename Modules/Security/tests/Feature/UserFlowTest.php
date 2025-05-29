<?php

namespace Modules\Security\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Security\Models\Role;
use Modules\Security\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserFlowTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        config(['auth.defaults.guard' => 'sanctum']);

        foreach (
            [
                'security.users.index',
                'security.users.store',
                'security.users.update',
                'security.users.destroy',
                'security.users.change-password',
                'security.users.toggle-status',
            ] as $perm
        ) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }
    }

    /** @test */
    /** @test */
    public function can_list_users()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['security.users.index'])->create()
        );

        User::factory()->count(2)->create();

        $this->getJson('/api/v1/security/users')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    /** @test */
    public function can_create_user()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['security.users.store'])->create()
        );

        // Asegúrate de que el rol 'admin' exista en tu base de datos
        $role = Role::create(['name' => 'admin']);



        $payload = [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@erp.com',
            'password' => 'Secret@123',
            'password_confirmation' => 'Secret@123',
            'roles' => [$role->name], // asegúrate que 'admin' exista
        ];


        $this->postJson('/api/v1/security/users', $payload)
            ->assertCreated()
            ->assertJsonFragment(['email' => 'test@erp.com']);
    }


    /** @test */
    public function it_can_show_user()
    {
        $user = User::factory()->create();

        // ASIGNAR PERMISO CON SANCTUM
        Sanctum::actingAs(
            User::factory()->withPermission(['security.users.index'])->create()
        );

        $response = $this->getJson("/api/v1/security/users/{$user->id}");

        $response->assertOk()
            ->assertJsonFragment(['email' => $user->email]);
    }

    /** @test */
    public function it_can_update_user()
    {
        $user = User::factory()->create();
        // ASIGNAR PERMISO CON SANCTUM
        Sanctum::actingAs(
            User::factory()->withPermission(['security.users.update'])->create()
        );

        $role = Role::create(['name' => 'admin']);

        $payload = [
            'name' => 'Updated Name',
            'email' => 'updated@erp.com',
            'roles' => [$role->name], // asegúrate que 'admin' exista
        ];

        $response = $this->putJson("/api/v1/security/users/{$user->user_id}", $payload);

        $response->assertOk()
            ->assertJsonFragment(['email' => 'updated@erp.com']);
    }

    /** @test */
    public function it_can_delete_user()
    {
        $user = User::factory()->create();

        //SANCTUM PERMISO
        Sanctum::actingAs(
            User::factory()->withPermission(['security.users.destroy'])->create()
        );

        $response = $this->deleteJson("/api/v1/security/users/{$user->user_id}");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Usuario eliminado correctamente']);
    }

    /** @test */
    public function it_can_change_password()
    {
        Sanctum::actingAs(
            User::factory()->withPermission(['security.users.change-password'])->create()
        );

        $user = User::factory()->create();

        $payload = [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!'
        ];

        $response = $this->postJson("/api/v1/security/users/{$user->user_id}/change-password", $payload);

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Contraseña actualizada correctamente']);
    }
    /** @test */
    public function it_can_toggle_user_status()
    {
        $user = User::factory()->create(['status' => 'active']);

        Sanctum::actingAs(
            User::factory()->withPermission(['security.users.toggle-status'])->create()
        );

        $response = $this->postJson("/api/v1/security/users/{$user->user_id}/toggle-status");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'blocked']);
    }
}
