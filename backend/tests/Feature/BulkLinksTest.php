<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class BulkLinksTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    public function test_bulk_create_reports_each_item_independently(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        Link::factory()->create(['workspace_id' => $workspace->id, 'key' => 'dup']);

        $response = $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links/bulk', ['links' => [
            ['url' => 'https://one.com'],
            ['url' => 'https://two.com', 'key' => 'dup'],
            ['url' => 'ftp://bad.com'],
            ['nothing' => true],
            ['url' => 'https://five.com', 'key' => 'five'],
        ]])->assertCreated();

        $data = $response->json('data');
        $this->assertSame('https://one.com', $data[0]['url']);
        $this->assertSame('conflict', $data[1]['error']['code']);
        $this->assertSame('unprocessable_entity', $data[2]['error']['code']);
        $this->assertSame('unprocessable_entity', $data[3]['error']['code']);
        $this->assertSame('five', $data[4]['key']);
        $this->assertSame(3, $workspace->links()->count()); // existing + 2 successes
    }

    public function test_bulk_create_is_capped_at_100_items(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links/bulk', ['links' => array_fill(0, 101, ['url' => 'https://a.com'])])->assertStatus(422);
        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links/bulk', ['links' => []])->assertStatus(422);
    }

    public function test_bulk_update_applies_data_to_the_given_links_only(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $tag = Tag::factory()->create(['workspace_id' => $workspace->id]);
        [$a, $b, $c] = Link::factory()->count(3)->create(['workspace_id' => $workspace->id]);
        $foreign = Link::factory()->create();

        $this->actingInWorkspace($user, $workspace)->patchJson('/api/v1/links/bulk', [
            'linkIds' => [$a->id, $b->id, $foreign->id], 'data' => ['archived' => true, 'tagIds' => [$tag->id]],
        ])->assertOk()->assertJsonCount(2, 'data');

        $this->assertTrue($a->refresh()->archived && $b->refresh()->archived);
        $this->assertFalse($c->refresh()->archived);
        $this->assertFalse($foreign->refresh()->archived);
        $this->assertCount(1, $a->tags);
    }

    public function test_bulk_update_cannot_change_identity_fields(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $link = Link::factory()->create(['workspace_id' => $workspace->id]);

        $this->actingInWorkspace($user, $workspace)->patchJson('/api/v1/links/bulk', ['linkIds' => [$link->id], 'data' => ['key' => 'x', 'url' => 'https://x.com']])
            ->assertOk(); // unknown keys are dropped by validation, not applied
        $this->assertNotSame('x', $link->refresh()->key);
    }

    public function test_bulk_delete_removes_only_own_links_and_reports_the_count(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        [$a, $b] = Link::factory()->count(2)->create(['workspace_id' => $workspace->id]);
        $foreign = Link::factory()->create();

        $this->actingInWorkspace($user, $workspace)->deleteJson("/api/v1/links/bulk?linkIds={$a->id},{$b->id},{$foreign->id}")
            ->assertOk()->assertJsonPath('deletedCount', 2);

        $this->assertModelMissing($a);
        $this->assertModelExists($foreign);
        $this->actingInWorkspace($user, $workspace)->deleteJson('/api/v1/links/bulk')->assertStatus(400);
    }
}
