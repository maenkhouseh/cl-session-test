<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** Sanctum treats requests from a first-party SPA origin as stateful (session available). */
    private function spa(): static
    {
        return $this->withHeader('Referer', 'http://localhost:3000');
    }

    public function test_register_creates_user_and_personal_workspace(): void
    {
        $this->spa()->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace', 'email' => 'Ada@Example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertCreated()->assertJsonPath('data.email', 'ada@example.com');

        $user = User::firstWhere('email', 'ada@example.com');
        $this->assertNotNull($user);
        $this->assertCount(1, $user->workspaces);
        $this->assertSame('owner', $user->workspaces->first()->pivot->role);
        $this->assertSame('free', $user->workspaces->first()->plan->value);
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_validation_errors_use_the_error_envelope(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->spa()->postJson('/api/v1/auth/register', [
            'name' => '', 'email' => 'taken@example.com', 'password' => 'short', 'password_confirmation' => 'nope',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'unprocessable_entity')
            ->assertJsonStructure(['error' => ['code', 'message', 'doc_url', 'errors' => ['name', 'email', 'password']]]);
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        $this->spa()->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertOk()->assertJsonPath('data.email', $user->email);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_bad_credentials_is_unauthorized(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        $this->spa()->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnauthorized()->assertJsonPath('error.code', 'unauthorized');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $_) {
            $this->spa()->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'bad'])->assertUnauthorized();
        }

        $this->spa()->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'bad'])
            ->assertStatus(429)->assertJsonPath('error.code', 'rate_limit_exceeded');
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        foreach (range(1, 10) as $i) {
            $this->spa()->postJson('/api/v1/auth/register', ['name' => 'U', 'email' => "u{$i}@example.com", 'password' => 'password123', 'password_confirmation' => 'password123'])->assertCreated();
            $this->app['auth']->forgetGuards();
        }

        $this->spa()->postJson('/api/v1/auth/register', ['name' => 'U', 'email' => 'u11@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertStatus(429)->assertJsonPath('error.code', 'rate_limit_exceeded');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('error.code', 'unauthorized');
    }

    public function test_me_returns_the_current_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password');
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);
        $this->spa()->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-pass'])->assertOk();

        $this->spa()->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertGuest('web');
    }
}
