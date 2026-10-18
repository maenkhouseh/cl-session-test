<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class CreateLinkTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    private function create(array $payload, Plan $plan = Plan::Business)
    {
        [$user, $workspace] = $this->ownerWithWorkspace($plan);
        $this->workspace = $workspace;

        return $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', $payload);
    }

    private $workspace;

    public function test_creates_a_link_with_a_generated_key_on_the_default_domain(): void
    {
        $response = $this->create(['url' => 'https://example.com/landing'])
            ->assertCreated()
            ->assertJsonPath('data.domain', 'shortie.link')
            ->assertJsonPath('data.url', 'https://example.com/landing')
            ->assertJsonPath('data.clicks', 0)
            ->assertJsonPath('data.tags', []);

        $key = $response->json('data.key');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{7}$/', $key);
        $this->assertSame("https://shortie.link/{$key}", $response->json('data.shortLink'));
        $this->assertStringContainsString('/api/v1/qr?url=', $response->json('data.qrCode'));
        $this->assertSame(1, $this->workspace->refresh()->links_usage);
    }

    public function test_adds_https_when_the_scheme_is_missing(): void
    {
        $this->create(['url' => 'example.com/page'])->assertCreated()->assertJsonPath('data.url', 'https://example.com/page');
    }

    public function test_rejects_non_web_or_malformed_destinations(): void
    {
        foreach (['ftp://example.com', 'javascript:alert(1)', 'https://', 'http://exa mple.com'] as $bad) {
            $this->create(['url' => $bad])->assertStatus(422);
        }
        $this->create([])->assertStatus(422)->assertJsonPath('error.code', 'unprocessable_entity');
    }

    public function test_rejects_destinations_pointing_at_our_own_short_domains(): void
    {
        $this->create(['url' => 'https://shortie.link/abc'])->assertStatus(422)->assertJsonPath('error.code', 'unprocessable_entity');
    }

    public function test_accepts_a_custom_key_and_rejects_duplicates_case_insensitively(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $api = fn () => $this->actingInWorkspace($user, $workspace);

        $api()->postJson('/api/v1/links', ['url' => 'https://a.com', 'key' => 'Promo'])->assertCreated()->assertJsonPath('data.key', 'Promo');
        $api()->postJson('/api/v1/links', ['url' => 'https://b.com', 'key' => 'promo'])->assertStatus(409)->assertJsonPath('error.code', 'conflict');
    }

    public function test_rejects_reserved_and_malformed_keys(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();

        foreach (['api', 'Admin', 'has space', '-lead', 'x!'] as $key) {
            $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', ['url' => 'https://a.com', 'key' => $key])->assertStatus(422);
        }
    }

    public function test_key_length_and_prefix_shape_generated_keys(): void
    {
        $key = $this->create(['url' => 'https://a.com', 'keyLength' => 10, 'prefix' => 'go-'])->assertCreated()->json('data.key');

        $this->assertStringStartsWith('go-', $key);
        $this->assertSame(13, strlen($key));
    }

    public function test_uses_the_workspace_primary_verified_domain_by_default(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        Domain::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'go.acme.com', 'is_primary' => true]);

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', ['url' => 'https://a.com'])
            ->assertCreated()->assertJsonPath('data.domain', 'go.acme.com');
    }

    public function test_domain_must_belong_to_the_workspace_and_be_verified(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        [, $other] = $this->ownerWithWorkspace();
        Domain::factory()->create(['workspace_id' => $other->id, 'slug' => 'theirs.com']);
        Domain::factory()->unverified()->create(['workspace_id' => $workspace->id, 'slug' => 'pending.com']);

        foreach (['theirs.com', 'pending.com', 'unknown.com'] as $domain) {
            $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', ['url' => 'https://a.com', 'domain' => $domain])->assertStatus(422);
        }
    }

    public function test_same_key_may_exist_on_different_domains(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        Domain::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'go.acme.com']);

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', ['url' => 'https://a.com', 'key' => 'x1'])->assertCreated();
        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', ['url' => 'https://a.com', 'key' => 'x1', 'domain' => 'go.acme.com'])->assertCreated();
    }

    public function test_utm_fields_are_written_onto_the_url(): void
    {
        $this->create(['url' => 'https://example.com/p?x=1', 'utm_source' => 'google', 'utm_medium' => 'cpc'])
            ->assertCreated()
            ->assertJsonPath('data.utm_source', 'google')
            ->assertJsonPath('data.utm_medium', 'cpc')
            ->assertJsonPath('data.utm_campaign', null)
            ->assertJsonPath('data.url', 'https://example.com/p?x=1&utm_source=google&utm_medium=cpc');
    }

    public function test_utm_fields_are_extracted_from_the_url(): void
    {
        $this->create(['url' => 'https://example.com/?utm_campaign=summer&ref=nl'])
            ->assertCreated()->assertJsonPath('data.utm_campaign', 'summer')->assertJsonPath('data.ref', 'nl');
    }

    public function test_free_plan_blocks_paid_options_with_a_clear_error(): void
    {
        foreach ([['password' => 'x'], ['geo' => ['US' => 'https://us.com']], ['rewrite' => true], ['expiresAt' => now()->addDay()->toIso8601String()]] as $extra) {
            $this->create(['url' => 'https://a.com', ...$extra], Plan::Free)
                ->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        }
    }

    public function test_free_plan_cannot_exceed_the_monthly_link_quota(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace(Plan::Free);
        $workspace->update(['links_usage' => 25]);

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', ['url' => 'https://a.com'])
            ->assertForbidden()->assertJsonPath('error.code', 'exceeded_limit');
        $this->assertSame(0, Link::count());
        $this->assertSame(25, $workspace->refresh()->links_usage);
    }

    public function test_password_is_stored_hashed_and_never_returned(): void
    {
        $response = $this->create(['url' => 'https://a.com', 'password' => 'hunter2'])
            ->assertCreated()->assertJsonPath('data.hasPassword', true)->assertJsonMissingPath('data.password');

        $this->assertTrue(Hash::check('hunter2', Link::find($response->json('data.id'))->password));
    }

    public function test_attaches_tags_by_id_and_by_name(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $a = Tag::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Alpha']);
        $b = Tag::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Beta']);

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', ['url' => 'https://a.com', 'tagIds' => [$a->id], 'tagNames' => ['beta']])
            ->assertCreated()->assertJsonCount(2, 'data.tags');
    }

    public function test_unknown_or_foreign_tags_and_folders_are_rejected(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $foreignTag = Tag::factory()->create();
        $foreignFolder = Folder::factory()->create();

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', ['url' => 'https://a.com', 'tagIds' => [$foreignTag->id]])->assertStatus(422);
        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', ['url' => 'https://a.com', 'tagNames' => ['nope']])->assertStatus(422);
        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', ['url' => 'https://a.com', 'folderId' => $foreignFolder->id])->assertStatus(422);
    }

    public function test_read_only_folders_reject_new_links(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        $folder = Folder::factory()->create(['workspace_id' => $workspace->id, 'access_level' => 'read']);

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', ['url' => 'https://a.com', 'folderId' => $folder->id])->assertForbidden();
    }

    public function test_external_id_is_unique_per_workspace(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', ['url' => 'https://a.com', 'externalId' => '123'])->assertCreated();
        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', ['url' => 'https://b.com', 'externalId' => '123'])->assertStatus(409);

        [$other, $otherWs] = $this->ownerWithWorkspace();
        $this->actingInWorkspace($other, $otherWs)->postJson('/api/v1/links', ['url' => 'https://a.com', 'externalId' => '123'])->assertCreated();
    }

    public function test_geo_targets_are_normalised_and_validated(): void
    {
        $this->create(['url' => 'https://a.com', 'geo' => ['us' => 'us.example.com', 'GB' => 'https://gb.example.com']])
            ->assertCreated()->assertJsonPath('data.geo.US', 'https://us.example.com')->assertJsonPath('data.geo.GB', 'https://gb.example.com');

        $this->create(['url' => 'https://a.com', 'geo' => ['USA' => 'https://us.example.com']])->assertStatus(422);
    }

    public function test_ab_test_variants_must_be_2_to_4_and_sum_to_100(): void
    {
        $variants = fn (array $pcts) => array_map(fn ($p, $i) => ['url' => "https://v{$i}.com", 'percentage' => $p], $pcts, array_keys($pcts));

        $this->create(['url' => 'https://a.com', 'testVariants' => $variants([50, 50])])
            ->assertCreated()->assertJsonCount(2, 'data.testVariants')->assertJsonPath('data.testVariants.0.percentage', 50);
        $this->create(['url' => 'https://a.com', 'testVariants' => $variants([50])])->assertStatus(422);
        $this->create(['url' => 'https://a.com', 'testVariants' => $variants([60, 60])])->assertStatus(422);
        $this->create(['url' => 'https://a.com', 'testVariants' => $variants([20, 20, 20, 20, 20])])->assertStatus(422);
    }

    public function test_creating_clears_a_cached_negative_lookup(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace();
        Cache::put('link:shortie.link:fresh', false, 60);

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/links', ['url' => 'https://a.com', 'key' => 'fresh'])->assertCreated();

        $this->assertFalse(Cache::has('link:shortie.link:fresh'));
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/links', ['url' => 'https://a.com'])->assertUnauthorized();
    }
}
