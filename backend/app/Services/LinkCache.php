<?php

namespace App\Services;

use App\Models\Link;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Redirect hot-path cache: `domain/key → link attributes`. A miss is cached too (as `false`)
 * so scans of random keys do not reach the database.
 *
 * Only plain arrays are stored: Laravel refuses to unserialize arbitrary classes from the cache
 * (`serializable_classes`), so a cached Eloquent model would come back as a broken object on Redis.
 */
class LinkCache
{
    public function key(string $domain, string $key): string
    {
        return 'link:'.strtolower($domain).':'.strtolower($key);
    }

    public function remember(string $domain, string $key, Closure $resolver): ?Link
    {
        $hit = Cache::remember($this->key($domain, $key), config('shortie.redirect_cache_ttl'), function () use ($resolver) {
            $link = $resolver();

            return $link ? $link->getAttributes() : false;
        });

        // newFromBuilder hydrates from raw column values without a query, exactly like a fresh read.
        return $hit ? (new Link)->newFromBuilder($hit) : null;
    }

    public function forget(string $domain, string $key): void
    {
        Cache::forget($this->key($domain, $key));
    }

    public function forgetLink(Link $link): void
    {
        $this->forget($link->domain, $link->key);

        // After save(), getPrevious() holds the pre-update values of the attributes that changed.
        $previous = $link->getPrevious();
        if (isset($previous['domain']) || isset($previous['key'])) {
            $this->forget($previous['domain'] ?? $link->domain, $previous['key'] ?? $link->key);
        }
    }
}
