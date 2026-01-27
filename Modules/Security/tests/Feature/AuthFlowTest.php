<?php

namespace Modules\Security\Tests\Feature;

use Modules\Security\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'username' => 'admin',
            'password_hash' => bcrypt('Secret@123')
        ]);

        $response = $this->postJson('/api/v1/security/login', [
            'username' => 'admin',
            'password' => 'Secret@123' // ✅ Texto plano
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    /** @test */
    public function login_fails_with_invalid_credentials()
    {
        User::factory()->create(['username' => 'admin', 'password_hash' => bcrypt('Secret@123')]);

        $response = $this->postJson('/api/v1/security/login', [
            'username' => 'admin@erp.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    /** @test */
    public function authenticated_user_can_access_me()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/security/me');

        $response->assertOk()
            ->assertJsonFragment(['email' => $user->email]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_me()
    {
        $response = $this->getJson('/api/v1/security/me');
        $response->assertStatus(401);
    }

    /** @test */
    public function authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/security/logout');

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Sesión cerrada correctamente.']);
    }
}
