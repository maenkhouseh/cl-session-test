<?php

namespace Tests\Feature;

use App\Actions\Links\FlushClicks;
use App\Enums\Plan;
use App\Models\Domain;
use App\Models\Link;
use App\Services\ClickBuffer;
use App\Services\LinkCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';

    private const ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        app(ClickBuffer::class)->clear();
    }

    protected function tearDown(): void
    {
        app(ClickBuffer::class)->clear();
        parent::tearDown();
    }

    private function link(array $attrs = [], Plan $plan = Plan::Business): Link
    {
        [, $workspace] = $this->ownerWithWorkspace($plan);

        return Link::factory()->create(['workspace_id' => $workspace->id, 'key' => 'go', 'url' => 'https://dest.example.com/landing', ...$attrs]);
    }

    private function visit(string $path = '/go', array $headers = [], string $host = 'shortie.link')
    {
        // flushHeaders(): the test client otherwise carries headers over from the previous request.
        return $this->flushHeaders()->withHeaders(['User-Agent' => self::CHROME, ...$headers])->get("http://{$host}{$path}");
    }

    private function flush(): int
    {
        return app(FlushClicks::class)();
    }

    // ---- basics ------------------------------------------------------------------------------

    public function test_redirects_with_302_and_no_cache_headers(): void
    {
        $this->link();

        $response = $this->visit()->assertRedirect('https://dest.example.com/landing')->assertStatus(302);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    public function test_indexable_links_omit_the_noindex_header(): void
    {
        $this->link(['do_index' => true]);

        $this->assertNull($this->visit()->headers->get('X-Robots-Tag'));
    }

    public function test_key_lookup_is_case_insensitive(): void
    {
        $this->link(['key' => 'MixedCase']);

        $this->visit('/mixedcase')->assertRedirect('https://dest.example.com/landing');
        $this->visit('/MIXEDCASE')->assertRedirect('https://dest.example.com/landing');
    }

    public function test_keys_may_contain_slashes(): void
    {
        $this->link(['key' => 'docs/start']);

        $this->visit('/docs/start')->assertRedirect('https://dest.example.com/landing');
    }

    public function test_unknown_key_and_unknown_domain_are_404(): void
    {
        $this->link();

        $this->visit('/nope')->assertNotFound();
        $this->visit('/go', host: 'other.example.org')->assertNotFound();
    }

    public function test_domain_level_not_found_url_is_honoured(): void
    {
        $link = $this->link(['domain' => 'go.acme.com']);
        Domain::factory()->create(['workspace_id' => $link->workspace_id, 'slug' => 'go.acme.com', 'not_found_url' => 'https://acme.com/404']);

        $this->visit('/missing', host: 'go.acme.com')->assertRedirect('https://acme.com/404');
    }

    public function test_domain_root_redirects_to_the_placeholder_or_shows_a_page(): void
    {
        $link = $this->link(['domain' => 'go.acme.com']);
        $this->visit('/', host: 'go.acme.com')->assertOk();

        Domain::factory()->create(['workspace_id' => $link->workspace_id, 'slug' => 'go.acme.com', 'placeholder_url' => 'https://acme.com']);
        $this->visit('/', host: 'go.acme.com')->assertRedirect('https://acme.com');
    }

    public function test_api_paths_are_never_captured_by_the_redirect_route(): void
    {
        $this->link(['key' => 'apix']);

        $this->getJson('/api/v1/definitely-missing')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }

    public function test_query_parameters_pass_through_and_destination_params_win(): void
    {
        $this->link(['url' => 'https://dest.example.com/p?plan=pro']);

        $this->visit('/go?plan=free&campaign=x&qr=1&no_track=0')
            ->assertRedirect('https://dest.example.com/p?plan=pro&campaign=x');
    }

    // ---- caching -----------------------------------------------------------------------------

    public function test_repeat_visits_are_served_without_touching_the_database(): void
    {
        $this->link();
        $this->visit()->assertRedirect(); // warm link + UA caches

        DB::enableQueryLog();
        $this->visit()->assertRedirect();

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_misses_are_cached_so_scans_do_not_hit_the_database(): void
    {
        $this->visit('/ghost')->assertNotFound();
        DB::flushQueryLog();
        DB::enableQueryLog();

        // Only the (rare) domain fallback lookup may run; the link lookup itself must not.
        $this->visit('/ghost')->assertNotFound();

        $this->assertEmpty(array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], '"links"')));
    }

    // ---- state -------------------------------------------------------------------------------

    public function test_expired_links_return_410_or_the_expired_url(): void
    {
        $this->link(['expires_at' => now()->subMinute()]);
        $this->visit()->assertStatus(410);

        Link::query()->update(['expired_url' => 'https://dest.example.com/expired']);
        app(LinkCache::class)->forget('shortie.link', 'go');
        $this->visit()->assertRedirect('https://dest.example.com/expired');
    }

    public function test_future_expiry_still_redirects(): void
    {
        $this->link(['expires_at' => now()->addDay()]);

        $this->visit()->assertRedirect('https://dest.example.com/landing');
    }

    public function test_disabled_links_are_404(): void
    {
        $this->link(['disabled_at' => now()]);

        $this->visit()->assertNotFound();
    }

    public function test_archived_links_keep_redirecting(): void
    {
        $this->link(['archived' => true]);

        $this->visit()->assertRedirect('https://dest.example.com/landing');
    }

    // ---- password ----------------------------------------------------------------------------

    public function test_password_protected_links_show_a_form_and_do_not_count_a_click(): void
    {
        $this->link(['password' => Hash::make('letmein')]);

        $this->visit()->assertOk()->assertSee('password protected', false);
        $this->assertSame(0, app(ClickBuffer::class)->size());
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->link(['password' => Hash::make('letmein')]);

        $this->post('http://shortie.link/go', ['password' => 'nope'], ['User-Agent' => self::CHROME])
            ->assertStatus(401)->assertSee('Incorrect password', false);
    }

    public function test_correct_password_unlocks_via_a_signed_cookie_then_redirects_and_counts(): void
    {
        $link = $this->link(['password' => Hash::make('letmein')]);

        $unlock = $this->post('http://shortie.link/go', ['password' => 'letmein'], ['User-Agent' => self::CHROME])->assertRedirect('/go');
        $cookie = collect($unlock->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'sp_'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());

        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->visit()->assertRedirect('https://dest.example.com/landing');
        $this->assertSame(1, app(ClickBuffer::class)->size());

        // A forged cookie, or one issued before the password changed, must not unlock.
        $this->withUnencryptedCookie($cookie->getName(), 'forged')->visit()->assertOk()->assertSee('password protected', false);
        $link->update(['password' => Hash::make('different')]);
        app(LinkCache::class)->forgetLink($link);
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->visit()->assertOk()->assertSee('password protected', false);
    }

    // ---- preview / cloaking ------------------------------------------------------------------

    public function test_social_crawlers_get_open_graph_html_for_custom_preview_links(): void
    {
        $this->link(['proxy' => true, 'title' => 'Big News', 'description' => 'Read it', 'image' => 'https://cdn.example.com/og.png']);

        $this->visit(headers: ['User-Agent' => 'Twitterbot/1.0'])->assertOk()
            ->assertSee('og:title" content="Big News"', false)->assertSee('https://cdn.example.com/og.png', false);
        $this->assertSame(0, app(ClickBuffer::class)->size());

        $this->visit()->assertRedirect('https://dest.example.com/landing');
    }

    public function test_cloaked_links_render_the_destination_in_a_frame(): void
    {
        $this->link(['rewrite' => true]);

        $this->visit()->assertOk()->assertSee('<iframe src="https://dest.example.com/landing"', false);
        $this->assertSame(1, app(ClickBuffer::class)->size());
    }

    // ---- targeting ---------------------------------------------------------------------------

    public function test_geo_targeting_uses_the_edge_country_header(): void
    {
        $this->link(['geo' => ['DE' => 'https://de.example.com', 'US' => 'https://us.example.com']]);

        $this->visit(headers: ['CF-IPCountry' => 'DE'])->assertRedirect('https://de.example.com');
        $this->visit(headers: ['X-Vercel-IP-Country' => 'us'])->assertRedirect('https://us.example.com');
        $this->visit(headers: ['CF-IPCountry' => 'FR'])->assertRedirect('https://dest.example.com/landing');
        $this->visit()->assertRedirect('https://dest.example.com/landing');
    }

    public function test_device_targeting_for_ios_and_android(): void
    {
        $this->link(['ios' => 'https://apps.apple.com/app/x', 'android' => 'https://play.google.com/store/apps/x']);

        $this->visit(headers: ['User-Agent' => self::IPHONE])->assertRedirect('https://apps.apple.com/app/x');
        $this->visit(headers: ['User-Agent' => self::ANDROID])->assertRedirect('https://play.google.com/store/apps/x');
        $this->visit()->assertRedirect('https://dest.example.com/landing');
    }

    public function test_ab_tests_split_traffic_and_stick_per_visitor(): void
    {
        $this->link(['test_variants' => [['url' => 'https://a.example.com', 'percentage' => 50], ['url' => 'https://b.example.com', 'percentage' => 50]]]);

        $seen = collect(range(1, 60))->map(fn () => $this->visit()->headers->get('Location'))->unique()->sort()->values();
        $this->assertSame(['https://a.example.com', 'https://b.example.com'], $seen->all());

        $first = $this->visit();
        $cookie = collect($first->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'ab_'));
        $this->assertNotNull($cookie);
        $sticky = collect(range(1, 15))->map(fn () => $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->visit()->headers->get('Location'))->unique();
        $this->assertCount(1, $sticky);
        $this->assertSame($first->headers->get('Location'), $sticky->first());
    }

    public function test_a_completed_ab_test_falls_back_to_the_default_url(): void
    {
        $this->link(['test_completed_at' => now(), 'test_variants' => [['url' => 'https://a.example.com', 'percentage' => 50], ['url' => 'https://b.example.com', 'percentage' => 50]]]);

        $this->visit()->assertRedirect('https://dest.example.com/landing');
    }

    // ---- click tracking ----------------------------------------------------------------------

    public function test_a_click_is_buffered_then_flushed_into_events_and_counters(): void
    {
        $link = $this->link(['utm_source' => 'nl']);

        $this->visit(headers: ['CF-IPCountry' => 'DE', 'CF-IPCity' => 'Berlin', 'Referer' => 'https://news.ycombinator.com/item?id=1'])->assertRedirect();
        $this->assertSame(1, app(ClickBuffer::class)->size());
        $this->assertSame(0, DB::table('click_events')->count()); // nothing written on the request path

        $this->assertSame(1, $this->flush());

        $event = DB::table('click_events')->first();
        $this->assertSame($link->id, trim($event->link_id));
        $this->assertSame('DE', trim($event->country));
        $this->assertSame('EU', trim($event->continent));
        $this->assertSame('Berlin', $event->city);
        $this->assertSame('Desktop', $event->device);
        $this->assertSame('Chrome', $event->browser);
        $this->assertSame('Mac', $event->os);
        $this->assertSame('news.ycombinator.com', $event->referer);
        $this->assertSame('https://news.ycombinator.com/item?id=1', $event->referer_url);
        $this->assertSame('nl', $event->utm_source);
        $this->assertTrue((bool) $event->is_unique);
        $this->assertFalse((bool) $event->qr);

        $link->refresh();
        $this->assertSame(1, $link->clicks);
        $this->assertNotNull($link->last_clicked_at);
        $this->assertSame(1, $link->workspace->refresh()->events_usage);
    }

    public function test_direct_visits_and_qr_scans_are_classified(): void
    {
        $this->link();
        $this->visit('/go?qr=1');
        $this->flush();

        $event = DB::table('click_events')->first();
        $this->assertSame('(direct)', $event->referer);
        $this->assertTrue((bool) $event->qr);
    }

    public function test_repeat_visits_from_the_same_visitor_are_not_unique(): void
    {
        $this->link();
        $this->visit();
        $this->visit();
        $this->flush();

        $this->assertSame([true, false], DB::table('click_events')->orderBy('id')->pluck('is_unique')->map(fn ($v) => (bool) $v)->all());
        $this->assertSame(2, Link::first()->clicks);
    }

    public function test_bots_and_no_track_requests_are_not_counted_but_still_redirect(): void
    {
        $this->link();

        $this->visit(headers: ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->assertRedirect();
        $this->visit(headers: ['User-Agent' => ''])->assertRedirect();
        $this->visit('/go?no_track=1')->assertRedirect();

        $this->assertSame(0, app(ClickBuffer::class)->size());
    }

    public function test_conversion_tracking_links_carry_the_click_id_to_the_destination(): void
    {
        $this->link(['track_conversion' => true]);

        $location = $this->visit()->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->flush();

        $this->assertSame(DB::table('click_events')->value('click_id'), $query['shortie_id']);
        $this->assertSame(16, strlen($query['shortie_id']));
    }

    public function test_links_without_conversion_tracking_do_not_leak_the_click_id(): void
    {
        $this->link();

        $this->assertStringNotContainsString('shortie_id', $this->visit()->headers->get('Location'));
    }

    public function test_events_over_the_plan_quota_are_dropped_but_links_keep_working(): void
    {
        $link = $this->link([], Plan::Free);
        $link->workspace->update(['events_usage' => 999]); // free plan: 1,000 / month

        foreach (range(1, 5) as $_) {
            $this->visit()->assertRedirect();
        }

        $this->assertSame(1, $this->flush());
        $this->assertSame(1, $link->refresh()->clicks);
        $this->assertSame(1000, $link->workspace->refresh()->events_usage);
    }

    public function test_flushing_aggregates_counters_across_links_and_workspaces(): void
    {
        $a = $this->link(['key' => 'a']);
        $b = $this->link(['key' => 'b']);

        foreach (['/a', '/a', '/a', '/b'] as $i => $path) {
            $this->visit($path, ['X-Forwarded-For' => "10.0.0.{$i}"]);
        }

        $this->assertSame(4, $this->flush());
        $this->assertSame(3, $a->refresh()->clicks);
        $this->assertSame(1, $b->refresh()->clicks);
        $this->assertSame(0, $this->flush()); // buffer drained
    }

    public function test_the_buffer_drains_in_bounded_batches(): void
    {
        $this->link();
        foreach (range(1, 5) as $_) {
            $this->visit();
        }

        $this->assertSame(3, app(FlushClicks::class)(3));
        $this->assertSame(2, app(ClickBuffer::class)->size());
        $this->assertSame(2, app(FlushClicks::class)(3));
    }

    public function test_events_for_a_deleted_workspace_are_discarded_safely(): void
    {
        $link = $this->link();
        $this->visit();
        $link->workspace->delete();

        $this->assertSame(0, $this->flush());
        $this->assertSame(0, DB::table('click_events')->count());
    }
}
