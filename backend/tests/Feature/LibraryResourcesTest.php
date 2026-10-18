<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\UtmTemplate;
use App\Services\DomainVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class LibraryResourcesTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    // ---- Tags -------------------------------------------------------------------------------------

    public function test_tag_crud_with_case_insensitive_unique_names(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $api = fn () => $this->actingInWorkspace($user, $workspace);

        $id = $api()->postJson('/api/v1/tags', ['name' => 'Launch', 'color' => 'red'])->assertCreated()->assertJsonPath('data.color', 'red')->json('data.id');
        $api()->postJson('/api/v1/tags', ['name' => 'launch'])->assertStatus(409);
        $api()->postJson('/api/v1/tags', ['name' => 'X', 'color' => 'chartreuse'])->assertStatus(422);
        $api()->patchJson("/api/v1/tags/{$id}", ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');
        $api()->getJson('/api/v1/tags')->assertJsonCount(1, 'data');
        $api()->deleteJson("/api/v1/tags/{$id}")->assertOk();
        $api()->getJson('/api/v1/tags')->assertJsonCount(0, 'data');
    }

    public function test_same_tag_name_may_exist_in_different_workspaces_but_ids_do_not_cross(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $foreign = Tag::factory()->create(['name' => 'Shared']);

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/tags', ['name' => 'Shared'])->assertCreated();
        $this->actingInWorkspace($user, $workspace)->patchJson("/api/v1/tags/{$foreign->id}", ['name' => 'x'])->assertNotFound();
        $this->actingInWorkspace($user, $workspace)->deleteJson("/api/v1/tags/{$foreign->id}")->assertNotFound();
    }

    public function test_deleting_a_tag_detaches_it_from_links(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $tag = Tag::factory()->create(['workspace_id' => $workspace->id]);
        $link = Link::factory()->create(['workspace_id' => $workspace->id]);
        $link->tags()->attach($tag->id);

        $this->actingInWorkspace($user, $workspace)->deleteJson("/api/v1/tags/{$tag->id}")->assertOk();

        $this->assertModelExists($link);
        $this->assertSame(0, $link->tags()->count());
    }

    // ---- Folders ----------------------------------------------------------------------------------

    public function test_folder_crud_and_link_counts(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $api = fn () => $this->actingInWorkspace($user, $workspace);

        $id = $api()->postJson('/api/v1/folders', ['name' => 'Marketing', 'accessLevel' => 'read'])->assertCreated()->assertJsonPath('data.accessLevel', 'read')->json('data.id');
        Link::factory()->count(2)->create(['workspace_id' => $workspace->id, 'folder_id' => $id]);

        $api()->getJson('/api/v1/folders')->assertJsonPath('data.0.linkCount', 2);
        $api()->postJson('/api/v1/folders', ['name' => 'marketing'])->assertStatus(409);
        $api()->postJson('/api/v1/folders', ['name' => 'Bad', 'accessLevel' => 'admin'])->assertStatus(422);
        $api()->patchJson("/api/v1/folders/{$id}", ['accessLevel' => 'write'])->assertOk()->assertJsonPath('data.accessLevel', 'write');
    }

    public function test_deleting_a_folder_keeps_its_links_unfiled(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $folder = Folder::factory()->create(['workspace_id' => $workspace->id]);
        $link = Link::factory()->create(['workspace_id' => $workspace->id, 'folder_id' => $folder->id]);

        $this->actingInWorkspace($user, $workspace)->deleteJson("/api/v1/folders/{$folder->id}")->assertOk();

        $this->assertNull($link->refresh()->folder_id);
    }

    public function test_folder_limits_follow_the_plan(): void
    {
        [$free, $freeWs] = $this->ownerWithWorkspace(Plan::Free);
        $this->actingInWorkspace($free, $freeWs)->postJson('/api/v1/folders', ['name' => 'Nope'])
            ->assertForbidden()->assertJsonPath('error.code', 'exceeded_limit');

        [$user, $workspace] = $this->ownerWithWorkspace(Plan::Business);
        Folder::factory()->count(20)->create(['workspace_id' => $workspace->id]);
        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/folders', ['name' => 'One too many'])->assertForbidden();
    }

    // ---- UTM templates ----------------------------------------------------------------------------

    public function test_utm_template_crud(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $api = fn () => $this->actingInWorkspace($user, $workspace);

        $id = $api()->postJson('/api/v1/utm-templates', ['name' => 'Newsletter', 'utm_source' => 'newsletter', 'utm_medium' => 'email'])
            ->assertCreated()->assertJsonPath('data.utm_source', 'newsletter')->json('data.id');
        $api()->postJson('/api/v1/utm-templates', ['name' => 'NEWSLETTER'])->assertStatus(409);
        $api()->patchJson("/api/v1/utm-templates/{$id}", ['utm_campaign' => 'q4'])->assertOk()->assertJsonPath('data.utm_campaign', 'q4')->assertJsonPath('data.utm_source', 'newsletter');
        $api()->getJson('/api/v1/utm-templates')->assertJsonCount(1, 'data');
        $api()->deleteJson("/api/v1/utm-templates/{$id}")->assertOk();
        $this->assertSame(0, UtmTemplate::count());
    }

    // ---- Domains ----------------------------------------------------------------------------------

    public function test_first_domain_becomes_primary_and_returns_dns_instructions(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/domains', ['slug' => 'Go.Acme.com'])
            ->assertCreated()->assertJsonPath('data.slug', 'go.acme.com')->assertJsonPath('data.primary', true)
            ->assertJsonPath('data.verified', false)->assertJsonPath('data.dns.type', 'CNAME')->assertJsonPath('data.dns.value', config('shortie.dns_target'));

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/domains', ['slug' => 'second.acme.com'])->assertCreated()->assertJsonPath('data.primary', false);
    }

    public function test_domain_validation_and_global_uniqueness(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        [, $other] = $this->ownerWithWorkspace();
        Domain::factory()->create(['workspace_id' => $other->id, 'slug' => 'taken.com']);
        $api = fn () => $this->actingInWorkspace($user, $workspace);

        foreach (['not a domain', 'nodot', 'http://x.com', '-bad.com'] as $slug) {
            $api()->postJson('/api/v1/domains', ['slug' => $slug])->assertStatus(422);
        }
        $api()->postJson('/api/v1/domains', ['slug' => 'taken.com'])->assertStatus(409);
        $api()->postJson('/api/v1/domains', ['slug' => 'shortie.link'])->assertStatus(409);
    }

    public function test_domain_count_follows_the_plan(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace(Plan::Free); // 3 domains
        Domain::factory()->count(3)->create(['workspace_id' => $workspace->id]);

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/domains', ['slug' => 'fourth.com'])
            ->assertForbidden()->assertJsonPath('error.code', 'exceeded_limit');
    }

    public function test_verification_marks_the_domain_via_the_verifier(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $domain = Domain::factory()->unverified()->create(['workspace_id' => $workspace->id]);

        $this->app->instance(DomainVerifier::class, new class implements DomainVerifier
        {
            public function verify(string $host): bool
            {
                return true;
            }
        });
        $this->actingInWorkspace($user, $workspace)->postJson("/api/v1/domains/{$domain->id}/verify")->assertOk()->assertJsonPath('data.verified', true);
        $this->assertNotNull($domain->refresh()->verified_at);

        $this->app->instance(DomainVerifier::class, new class implements DomainVerifier
        {
            public function verify(string $host): bool
            {
                return false;
            }
        });
        $this->actingInWorkspace($user, $workspace)->postJson("/api/v1/domains/{$domain->id}/verify")->assertJsonPath('data.verified', false);
    }

    public function test_only_one_primary_domain_and_it_must_be_verified(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $a = Domain::factory()->create(['workspace_id' => $workspace->id, 'is_primary' => true]);
        $b = Domain::factory()->create(['workspace_id' => $workspace->id]);
        $unverified = Domain::factory()->unverified()->create(['workspace_id' => $workspace->id]);

        $this->actingInWorkspace($user, $workspace)->patchJson("/api/v1/domains/{$unverified->id}", ['primary' => true])->assertStatus(422);
        $this->actingInWorkspace($user, $workspace)->patchJson("/api/v1/domains/{$b->id}", ['primary' => true])->assertOk();

        $this->assertFalse($a->refresh()->is_primary);
        $this->assertTrue($b->refresh()->is_primary);
    }

    public function test_domain_fallback_urls_are_normalised_and_slug_is_immutable(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $domain = Domain::factory()->create(['workspace_id' => $workspace->id]);

        $this->actingInWorkspace($user, $workspace)->patchJson("/api/v1/domains/{$domain->id}", ['notFoundUrl' => 'acme.com/404', 'placeholder' => 'acme.com'])
            ->assertOk()->assertJsonPath('data.notFoundUrl', 'https://acme.com/404')->assertJsonPath('data.placeholder', 'https://acme.com');
        $this->actingInWorkspace($user, $workspace)->patchJson("/api/v1/domains/{$domain->id}", ['slug' => 'other.com'])->assertStatus(422);
    }

    public function test_a_domain_with_links_cannot_be_deleted(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $domain = Domain::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'go.acme.com']);
        $link = Link::factory()->create(['workspace_id' => $workspace->id, 'domain' => 'go.acme.com']);

        $this->actingInWorkspace($user, $workspace)->deleteJson("/api/v1/domains/{$domain->id}")->assertStatus(400);
        $link->delete();
        $this->actingInWorkspace($user, $workspace)->deleteJson("/api/v1/domains/{$domain->id}")->assertOk();
        $this->assertModelMissing($domain);
    }

    public function test_other_workspaces_domains_are_not_addressable(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $foreign = Domain::factory()->create();

        $this->actingInWorkspace($user, $workspace)->postJson("/api/v1/domains/{$foreign->id}/verify")->assertNotFound();
        $this->actingInWorkspace($user, $workspace)->deleteJson("/api/v1/domains/{$foreign->id}")->assertNotFound();
    }
}
