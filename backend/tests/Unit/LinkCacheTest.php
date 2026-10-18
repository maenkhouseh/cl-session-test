<?php

namespace Tests\Unit;

use App\Models\Link;
use App\Services\LinkCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Runs on the real Redis cache store: cached values must survive production (de)serialization. */
class LinkCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cached_link_comes_back_as_a_fully_usable_model(): void
    {
        $link = Link::factory()->create([
            'domain' => 'go.acme.com', 'key' => 'Promo', 'expires_at' => now()->addDay(), 'geo' => ['DE' => 'https://de.example.com'],
            'test_variants' => [['url' => 'https://a.com', 'percentage' => 50], ['url' => 'https://b.com', 'percentage' => 50]], 'proxy' => true,
        ]);
        $cache = app(LinkCache::class);

        $first = $cache->remember('go.acme.com', 'promo', fn () => Link::query()->forDomainKey('go.acme.com', 'promo')->first());
        $queries = 0;
        \DB::listen(function () use (&$queries) {
            $queries++;
        });
        $second = $cache->remember('go.acme.com', 'promo', fn () => $this->fail('resolver must not run on a cache hit'));

        $this->assertSame(0, $queries);
        $this->assertInstanceOf(Link::class, $second);
        $this->assertSame($link->id, $second->id);
        $this->assertSame('Promo', $second->key);
        $this->assertSame(['DE' => 'https://de.example.com'], $second->geo);       // json casts survive
        $this->assertCount(2, $second->test_variants);
        $this->assertTrue($second->proxy);                                           // boolean casts survive
        $this->assertFalse($second->isExpired());                                    // date casts survive
        $this->assertEquals($first->only(['id', 'url', 'workspace_id']), $second->only(['id', 'url', 'workspace_id']));
    }

    public function test_only_plain_data_is_stored_in_the_cache(): void
    {
        Link::factory()->create(['domain' => 'go.acme.com', 'key' => 'plain']);
        $cache = app(LinkCache::class);
        $cache->remember('go.acme.com', 'plain', fn () => Link::query()->forDomainKey('go.acme.com', 'plain')->first());

        $stored = Cache::get($cache->key('go.acme.com', 'plain'));

        $this->assertIsArray($stored);
        $this->assertSame($stored, unserialize(serialize($stored), ['allowed_classes' => false]));
    }

    public function test_misses_are_cached_as_misses(): void
    {
        $cache = app(LinkCache::class);
        $calls = 0;
        $resolve = function () use (&$calls) {
            $calls++;

            return null;
        };

        $this->assertNull($cache->remember('go.acme.com', 'ghost', $resolve));
        $this->assertNull($cache->remember('go.acme.com', 'ghost', $resolve));
        $this->assertSame(1, $calls);
    }
}
