<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Models\ApiKey;
use App\Support\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    public function test_creation_returns_the_secret_exactly_once_and_stores_only_a_hash(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();

        $created = $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/api-keys', ['name' => 'CI'])
            ->assertCreated()->assertJsonPath('data.name', 'CI')->json('data');

        $this->assertStringStartsWith('shrt_', $created['key']);
        $this->assertDatabaseMissing('api_keys', ['key_hash' => $created['key']]);

        $this->actingInWorkspace($user, $workspace)->getJson('/api/v1/api-keys')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.key');
    }

    public function test_api_key_authenticates_and_implies_the_workspace(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $key = $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/api-keys', ['name' => 'CI'])->json('data.key');

        // Fresh app state: no session, no X-Workspace header — only the bearer key.
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($key)->getJson('/api/v1/workspace')
            ->assertOk()->assertJsonPath('data.id', $workspace->id);

        $this->assertNotNull(ApiKey::first()->last_used_at);
    }

    public function test_invalid_or_expired_keys_are_unauthorized(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();

        $this->withToken('shrt_doesnotexist')->getJson('/api/v1/workspace')
            ->assertUnauthorized()->assertJsonPath('error.code', 'unauthorized');

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/api-keys', ['name' => 'k']);
        ApiKey::query()->update(['expires_at' => now()->subDay()]);
        $key = 'shrt_expired';
        $workspace->apiKeys()->create(['user_id' => $user->id, 'name' => 'e', 'key_hash' => Hash::secret($key), 'key_preview' => 'x', 'expires_at' => now()->subDay()]);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($key)->getJson('/api/v1/workspace')->assertUnauthorized();
    }

    public function test_revoked_key_stops_working_and_other_workspaces_keys_are_untouchable(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        [$otherUser, $other] = $this->ownerWithWorkspace();
        $theirs = $this->actingInWorkspace($otherUser, $other)->postJson('/api/v1/api-keys', ['name' => 'x'])->json('data.id');
        $mine = $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/api-keys', ['name' => 'y']);

        $this->actingInWorkspace($user, $workspace)->deleteJson("/api/v1/api-keys/{$theirs}")->assertNotFound();
        $this->actingInWorkspace($user, $workspace)->deleteJson('/api/v1/api-keys/'.$mine->json('data.id'))->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($mine->json('data.key'))->getJson('/api/v1/workspace')->assertUnauthorized();
    }

    public function test_validates_name_and_future_expiry(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/api-keys', [])->assertStatus(422);
        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/api-keys', ['name' => 'k', 'expires_at' => now()->subDay()->toIso8601String()])->assertStatus(422);
    }

    public function test_rate_limit_follows_the_plan(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace(Plan::Free); // 60/min

        foreach (range(1, 60) as $_) {
            $this->actingInWorkspace($user, $workspace)->getJson('/api/v1/workspace')->assertOk();
        }

        $this->actingInWorkspace($user, $workspace)->getJson('/api/v1/workspace')
            ->assertStatus(429)->assertJsonPath('error.code', 'rate_limit_exceeded');
    }
}
