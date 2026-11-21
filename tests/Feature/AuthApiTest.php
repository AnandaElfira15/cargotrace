<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_always_creates_a_customer_even_if_role_is_sent(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Test User', 'email' => 'new@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'role' => 'admin', // mass-assignment attempt
        ])->assertCreated()
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'email']]);

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'role' => 'customer']);
    }

    public function test_register_validates_input(): void
    {
        $this->postJson('/api/register', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_with_valid_credentials_returns_a_token(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/login', ['email' => 'user@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'role']]);
    }

    public function test_login_with_invalid_credentials_is_rejected(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/login', ['email' => 'user@example.com', 'password' => 'wrong-password'])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_inactive_account_cannot_login(): void
    {
        User::factory()->inactive()->create(['email' => 'off@example.com']);

        $this->postJson('/api/login', ['email' => 'off@example.com', 'password' => 'password'])->assertForbidden();
    }

    public function test_protected_endpoints_require_authentication(): void
    {
        $this->getJson('/api/shipments')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/shipments', [])->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_profile_and_logout_revokes_token(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        $token = $this->postJson('/api/login', ['email' => 'me@example.com', 'password' => 'password'])->json('token');

        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('data.email', 'me@example.com');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unknown_api_route_returns_json_404_without_stack_trace(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertJsonMissingPath('trace')
            ->assertJsonMissingPath('exception');
    }
}
