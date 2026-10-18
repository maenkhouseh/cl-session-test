<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class ListLinksTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    private function ids($response): array
    {
        return array_column($response->json('data'), 'id');
    }

    public function test_lists_newest_first_with_cursorless_pagination_meta(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $links = collect(range(1, 5))->map(fn ($i) => Link::factory()->create(['workspace_id' => $workspace->id, 'created_at' => now()->subMinutes(10 - $i)]));

        $page1 = $this->actingInWorkspace($user, $workspace)->getJson('/api/v1/links?pageSize=2')->assertOk()
            ->assertJsonPath('meta', ['page' => 1, 'pageSize' => 2, 'hasMore' => true]);
        $this->assertSame([$links[4]->id, $links[3]->id], $this->ids($page1));

        $last = $this->actingInWorkspace($user, $workspace)->getJson('/api/v1/links?pageSize=2&page=3')->assertJsonPath('meta.hasMore', false);
        $this->assertSame([$links[0]->id], $this->ids($last));
    }

    public function test_only_returns_the_workspaces_own_links(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        Link::factory()->count(2)->create(['workspace_id' => $workspace->id]);
        Link::factory()->count(3)->create();

        $this->actingInWorkspace($user, $workspace)->getJson('/api/v1/links')->assertJsonCount(2, 'data');
    }

    public function test_archived_links_are_hidden_unless_requested(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        Link::factory()->create(['workspace_id' => $workspace->id]);
        Link::factory()->create(['workspace_id' => $workspace->id, 'archived' => true]);

        $this->actingInWorkspace($user, $workspace)->getJson('/api/v1/links')->assertJsonCount(1, 'data');
        $this->actingInWorkspace($user, $workspace)->getJson('/api/v1/links?showArchived=true')->assertJsonCount(2, 'data');
    }

    public function test_filters_by_domain_folder_user_and_tags(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $folder = Folder::factory()->create(['workspace_id' => $workspace->id]);
        $tagA = Tag::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Launch']);
        $tagB = Tag::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Blog']);
        $a = Link::factory()->create(['workspace_id' => $workspace->id, 'domain' => 'go.acme.com', 'folder_id' => $folder->id]);
        $b = Link::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
        $c = Link::factory()->create(['workspace_id' => $workspace->id]);
        $a->tags()->attach([$tagA->id, $tagB->id]);
        $b->tags()->attach($tagB->id);
        $api = fn (string $qs) => $this->ids($this->actingInWorkspace($user, $workspace)->getJson("/api/v1/links?{$qs}")->assertOk());

        $this->assertSame([$a->id], $api('domain=go.acme.com'));
        $this->assertSame([$a->id], $api("folderId={$folder->id}"));
        $this->assertEqualsCanonicalizing([$b->id, $c->id], $api('folderId=none'));
        $this->assertSame([$b->id], $api("userId={$user->id}"));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $api("tagIds={$tagB->id}"));
        $this->assertSame([$a->id], $api('tagNames[]=launch'));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $api("tagIds={$tagA->id},{$tagB->id}"));
        $this->assertSame([], $api('tagNames[]=ghost'));
    }

    public function test_search_matches_key_url_and_title_and_escapes_wildcards(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $byKey = Link::factory()->create(['workspace_id' => $workspace->id, 'key' => 'summer-sale']);
        $byUrl = Link::factory()->create(['workspace_id' => $workspace->id, 'url' => 'https://shop.com/SUMMER']);
        $byTitle = Link::factory()->create(['workspace_id' => $workspace->id, 'title' => 'Big Summer']);
        Link::factory()->create(['workspace_id' => $workspace->id, 'key' => 'winter', 'url' => 'https://x.com', 'title' => 'cold']);

        $found = $this->ids($this->actingInWorkspace($user, $workspace)->getJson('/api/v1/links?search=summer'));
        $this->assertEqualsCanonicalizing([$byKey->id, $byUrl->id, $byTitle->id], $found);

        $this->assertSame([], $this->ids($this->actingInWorkspace($user, $workspace)->getJson('/api/v1/links?search=%25')));
    }

    public function test_sorts_by_clicks_sale_amount_and_last_clicked(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $low = Link::factory()->create(['workspace_id' => $workspace->id, 'clicks' => 1, 'sale_amount' => 900, 'last_clicked_at' => now()->subDay()]);
        $high = Link::factory()->create(['workspace_id' => $workspace->id, 'clicks' => 50, 'sale_amount' => 10, 'last_clicked_at' => now()]);
        $never = Link::factory()->create(['workspace_id' => $workspace->id]);
        $api = fn (string $qs) => $this->ids($this->actingInWorkspace($user, $workspace)->getJson("/api/v1/links?{$qs}"));

        $this->assertSame([$high->id, $low->id, $never->id], array_slice($api('sortBy=clicks'), 0, 3));
        $this->assertSame([$low->id, $high->id], array_slice($api('sortBy=saleAmount'), 0, 2));
        $this->assertSame([$high->id, $low->id, $never->id], $api('sortBy=lastClicked'));
        $this->assertSame($never->id, $api('sortBy=clicks&sortOrder=asc')[0]);
    }

    public function test_invalid_query_params_are_rejected(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();

        foreach (['sortBy=password', 'pageSize=1000', 'page=0', 'sortOrder=sideways'] as $qs) {
            $this->actingInWorkspace($user, $workspace)->getJson("/api/v1/links?{$qs}")->assertStatus(422);
        }
    }

    public function test_listing_query_count_does_not_grow_with_the_number_of_links(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $tag = Tag::factory()->create(['workspace_id' => $workspace->id]);
        Link::factory()->count(30)->create(['workspace_id' => $workspace->id])->each(fn ($l) => $l->tags()->attach($tag->id));

        DB::enableQueryLog();
        $this->actingInWorkspace($user, $workspace)->getJson('/api/v1/links?pageSize=30')->assertJsonCount(30, 'data');
        $queries = collect(DB::getQueryLog())->pluck('query')->reject(fn ($q) => str_contains($q, 'sanctum') || str_contains($q, 'workspace_user'));

        // links + eager-loaded tags + workspace resolution — never one query per link.
        $this->assertLessThanOrEqual(4, $queries->count(), $queries->implode("\n"));
    }

    public function test_count_supports_filters_and_grouping(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $tag = Tag::factory()->create(['workspace_id' => $workspace->id]);
        $a = Link::factory()->create(['workspace_id' => $workspace->id, 'domain' => 'go.acme.com']);
        Link::factory()->count(2)->create(['workspace_id' => $workspace->id]);
        Link::factory()->create(['workspace_id' => $workspace->id, 'archived' => true]);
        $a->tags()->attach($tag->id);
        $api = fn (string $qs = '') => $this->actingInWorkspace($user, $workspace)->getJson("/api/v1/links/count?{$qs}")->assertOk();

        $api()->assertJsonPath('count', 3);
        $api('showArchived=true')->assertJsonPath('count', 4);
        $api('domain=go.acme.com')->assertJsonPath('count', 1);
        $this->assertEquals(['shortie.link' => 2, 'go.acme.com' => 1], collect($api('groupBy=domain')->json())->pluck('count', 'group')->map(fn ($c) => (int) $c)->all());
        $this->assertEquals([$tag->id => 1], collect($api('groupBy=tagId')->json())->pluck('count', 'group')->map(fn ($c) => (int) $c)->all());
    }
}
