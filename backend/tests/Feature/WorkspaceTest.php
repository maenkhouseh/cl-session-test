<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    public function test_lists_only_workspaces_the_user_belongs_to(): void
    {
        [$user, $mine] = $this->ownerWithWorkspace();
        $this->ownerWithWorkspace();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/workspaces')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_creates_workspace_with_generated_unique_slug(): void
    {
        [$user] = $this->ownerWithWorkspace();
        Workspace::factory()->create(['slug' => 'acme']);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/workspaces', ['name' => 'Acme'])
            ->assertCreated()->assertJsonPath('data.slug', 'acme-2')->assertJsonPath('data.plan', 'free');
    }

    public function test_rejects_duplicate_or_malformed_slug(): void
    {
        [$user] = $this->ownerWithWorkspace();
        Workspace::factory()->create(['slug' => 'taken']);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/workspaces', ['name' => 'X', 'slug' => 'taken'])->assertStatus(422);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/workspaces', ['name' => 'X', 'slug' => 'Bad Slug!'])->assertStatus(422);
    }

    public function test_show_requires_workspace_header(): void
    {
        [$user] = $this->ownerWithWorkspace();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/workspace')
            ->assertStatus(400)->assertJsonPath('error.code', 'bad_request');
    }

    public function test_cannot_access_a_workspace_you_do_not_belong_to(): void
    {
        [$user] = $this->ownerWithWorkspace();
        [, $other] = $this->ownerWithWorkspace();

        $this->actingInWorkspace($user, $other)->getJson('/api/v1/workspace')
            ->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }

    public function test_show_returns_plan_limits_and_usage(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace(Plan::Business);

        $this->actingInWorkspace($user, $workspace)->getJson('/api/v1/workspace')
            ->assertOk()
            ->assertJsonPath('data.plan', 'business')
            ->assertJsonPath('data.limits.links_per_month', 10000)
            ->assertJsonPath('data.usage.links', 0)
            ->assertJsonPath('data.systemDomains', ['shortie.link']);
    }

    public function test_workspace_can_be_addressed_by_id(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();

        $this->actingAs($user, 'sanctum')->withHeader('X-Workspace', $workspace->id)->getJson('/api/v1/workspace')
            ->assertOk()->assertJsonPath('data.id', $workspace->id);
    }

    public function test_owner_can_update_workspace(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();

        $this->actingInWorkspace($user, $workspace)->patchJson('/api/v1/workspace', ['name' => 'Renamed', 'slug' => 'renamed'])
            ->assertOk()->assertJsonPath('data.name', 'Renamed');

        $this->assertSame('renamed', $workspace->refresh()->slug);
    }

    public function test_update_may_keep_its_own_slug(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();

        $this->actingInWorkspace($user, $workspace)->patchJson('/api/v1/workspace', ['slug' => $workspace->slug])->assertOk();
    }

    public function test_member_cannot_update_or_delete_workspace(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();
        $member = $this->memberOf($workspace);

        $this->actingInWorkspace($member, $workspace)->patchJson('/api/v1/workspace', ['name' => 'Nope'])
            ->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        $this->actingInWorkspace($member, $workspace)->deleteJson('/api/v1/workspace')->assertForbidden();
    }

    public function test_owner_can_delete_workspace(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();

        $this->actingInWorkspace($user, $workspace)->deleteJson('/api/v1/workspace')->assertNoContent();
        $this->assertModelMissing($workspace);
    }
}
