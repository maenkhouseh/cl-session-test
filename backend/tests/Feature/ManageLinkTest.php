<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class ManageLinkTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    private function setUpLink(array $attrs = []): array
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $link = Link::factory()->create(['workspace_id' => $workspace->id, 'key' => 'abc123', 'url' => 'https://old.com', ...$attrs]);

        return [$user, $workspace, $link];
    }

    public function test_show_returns_the_link_with_tags(): void
    {
        [$user, $workspace, $link] = $this->setUpLink();
        $link->tags()->attach(Tag::factory()->create(['workspace_id' => $workspace->id, 'name' => 'T'])->id);

        $this->actingInWorkspace($user, $workspace)->getJson("/api/v1/links/{$link->id}")
            ->assertOk()->assertJsonPath('data.key', 'abc123')->assertJsonPath('data.tags.0.name', 'T');
    }

    public function test_links_of_other_workspaces_are_invisible_everywhere(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $foreign = Link::factory()->create();
        $api = fn () => $this->actingInWorkspace($user, $workspace);

        $api()->getJson("/api/v1/links/{$foreign->id}")->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $api()->patchJson("/api/v1/links/{$foreign->id}", ['title' => 'x'])->assertNotFound();
        $api()->deleteJson("/api/v1/links/{$foreign->id}")->assertNotFound();
        $this->assertModelExists($foreign);
    }

    public function test_info_finds_by_domain_and_key_or_external_id(): void
    {
        [$user, $workspace, $link] = $this->setUpLink(['external_id' => 'ext-1']);
        $api = fn () => $this->actingInWorkspace($user, $workspace);

        $api()->getJson('/api/v1/links/info?domain=shortie.link&key=ABC123')->assertOk()->assertJsonPath('data.id', $link->id);
        $api()->getJson('/api/v1/links/info?externalId=ext-1')->assertOk()->assertJsonPath('data.id', $link->id);
        $api()->getJson('/api/v1/links/info?domain=shortie.link&key=nope')->assertNotFound();
        $api()->getJson('/api/v1/links/info')->assertStatus(400);
    }

    public function test_update_changes_only_the_sent_fields(): void
    {
        [$user, $workspace, $link] = $this->setUpLink(['title' => 'Keep']);

        $this->actingInWorkspace($user, $workspace)->patchJson("/api/v1/links/{$link->id}", ['url' => 'new.com/x', 'archived' => true])
            ->assertOk()->assertJsonPath('data.url', 'https://new.com/x')->assertJsonPath('data.archived', true)->assertJsonPath('data.title', 'Keep');
    }

    public function test_update_busts_the_redirect_cache_for_old_and_new_keys(): void
    {
        [$user, $workspace, $link] = $this->setUpLink();
        Cache::put('link:shortie.link:abc123', 'stale', 60);
        Cache::put('link:shortie.link:renamed', false, 60);

        $this->actingInWorkspace($user, $workspace)->patchJson("/api/v1/links/{$link->id}", ['key' => 'renamed'])->assertOk()->assertJsonPath('data.key', 'renamed');

        $this->assertFalse(Cache::has('link:shortie.link:abc123'));
        $this->assertFalse(Cache::has('link:shortie.link:renamed'));
    }

    public function test_update_cannot_steal_an_existing_key(): void
    {
        [$user, $workspace, $link] = $this->setUpLink();
        Link::factory()->create(['workspace_id' => $workspace->id, 'key' => 'taken']);

        $this->actingInWorkspace($user, $workspace)->patchJson("/api/v1/links/{$link->id}", ['key' => 'TAKEN'])->assertStatus(409);
        $this->actingInWorkspace($user, $workspace)->patchJson("/api/v1/links/{$link->id}", ['key' => 'abc123'])->assertOk();
    }

    public function test_update_syncs_tags_and_clears_with_empty_array(): void
    {
        [$user, $workspace, $link] = $this->setUpLink();
        $tag = Tag::factory()->create(['workspace_id' => $workspace->id]);

        $this->actingInWorkspace($user, $workspace)->patchJson("/api/v1/links/{$link->id}", ['tagIds' => [$tag->id]])->assertJsonCount(1, 'data.tags');
        $this->actingInWorkspace($user, $workspace)->patchJson("/api/v1/links/{$link->id}", ['title' => 'untouched tags'])->assertJsonCount(1, 'data.tags');
        $this->actingInWorkspace($user, $workspace)->patchJson("/api/v1/links/{$link->id}", ['tagIds' => []])->assertJsonCount(0, 'data.tags');
    }

    public function test_update_can_clear_nullable_fields_and_the_password(): void
    {
        [$user, $workspace, $link] = $this->setUpLink(['title' => 'T', 'password' => bcrypt('x')]);

        $this->actingInWorkspace($user, $workspace)->patchJson("/api/v1/links/{$link->id}", ['title' => null, 'password' => null])
            ->assertOk()->assertJsonPath('data.title', null)->assertJsonPath('data.hasPassword', false);
    }

    public function test_delete_removes_the_link_and_its_cache_entry(): void
    {
        [$user, $workspace, $link] = $this->setUpLink();
        Cache::put('link:shortie.link:abc123', 'stale', 60);

        $this->actingInWorkspace($user, $workspace)->deleteJson("/api/v1/links/{$link->id}")->assertOk()->assertJsonPath('id', $link->id);

        $this->assertModelMissing($link);
        $this->assertFalse(Cache::has('link:shortie.link:abc123'));
    }

    public function test_upsert_creates_then_reuses_the_link_for_the_same_url(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $api = fn () => $this->actingInWorkspace($user, $workspace);

        $id = $api()->putJson('/api/v1/links/upsert', ['url' => 'https://same.com'])->assertCreated()->json('data.id');
        $api()->putJson('/api/v1/links/upsert', ['url' => 'https://same.com', 'title' => 'Updated'])
            ->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.title', 'Updated');
        $api()->putJson('/api/v1/links/upsert', ['url' => 'same.com'])->assertOk()->assertJsonPath('data.id', $id);

        $this->assertSame(1, $workspace->links()->count());
    }
}
