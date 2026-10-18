<?php

namespace App\Services;

use App\Models\Link;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Redirect hot-path cache: `domain/key → link payload`. A miss is cached too (as `false`)
 * so scans of random keys do not reach the database.
 */
class LinkCache
{
    public function key(string $domain, string $key): string
    {
        return 'link:'.strtolower($domain).':'.strtolower($key);
    }

    public function remember(string $domain, string $key, Closure $resolver): ?Link
    {
        $hit = Cache::remember($this->key($domain, $key), config('shortie.redirect_cache_ttl'), fn () => $resolver() ?? false);

        return $hit ?: null;
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
