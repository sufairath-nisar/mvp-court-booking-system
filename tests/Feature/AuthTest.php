<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_consumer_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Jane Player',
            'email'                 => 'jane@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'consumer')
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'role' => 'consumer']);
    }

    public function test_registration_always_creates_a_consumer_never_an_admin(): void
    {
        // Even if a malicious "role" is injected, it is ignored.
        $this->postJson('/api/auth/register', [
            'name'                  => 'Sneaky',
            'email'                 => 'sneaky@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role'                  => 'admin',
        ])->assertCreated();

        $this->assertSame(UserRole::CONSUMER, User::where('email', 'sneaky@example.com')->first()->role);
    }

    public function test_login_with_invalid_credentials_fails(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/auth/login', [
            'email'    => 'jane@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_consumer_cannot_access_admin_routes(): void
    {
        $consumer = User::factory()->create();

        $this->actingAs($consumer, 'sanctum')
            ->getJson('/api/admin/courts')
            ->assertStatus(403);
    }
}
