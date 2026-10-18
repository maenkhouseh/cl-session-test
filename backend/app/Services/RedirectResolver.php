<?php

namespace App\Services;

use App\Data\RedirectDecision;
use App\Models\Domain;
use App\Models\Link;
use App\Support\DestinationUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Decides what a visitor gets for domain/key. The link comes from the Redis-backed cache, so the
 * common path performs no database query at all.
 */
class RedirectResolver
{
    public function __construct(
        private LinkCache $cache,
        private UserAgentParser $agents,
        private GeoResolver $geo,
        private DestinationPicker $picker,
        private ClickRecorder $clicks,
    ) {}

    public function resolve(Request $request, string $key): RedirectDecision
    {
        $domain = strtolower($request->getHost());
        $link = $this->cache->remember($domain, $key, fn () => Link::query()->forDomainKey($domain, $key)->first());

        if (! $link || $link->disabled_at) {
            return RedirectDecision::to(RedirectDecision::NOT_FOUND, $this->domainFallback($domain, 'not_found_url'));
        }

        if ($link->isExpired()) {
            return RedirectDecision::to(RedirectDecision::EXPIRED, $link->expired_url ?: $this->domainFallback($domain, 'expired_url'), $link);
        }

        if ($link->password && ! $this->hasUnlocked($request, $link)) {
            return RedirectDecision::to(RedirectDecision::PASSWORD, null, $link);
        }

        $ua = $this->agents->parse($request->userAgent());

        if ($link->proxy && $ua['preview_bot']) {
            return RedirectDecision::to(RedirectDecision::PREVIEW, $link->url, $link);
        }

        [$destination, $variant] = $this->picker->pick(
            $link, $ua, $this->geo->resolve($request)['country'], $this->stickyVariant($request, $link),
        );

        $clickId = null;
        if (! $ua['bot'] && ! $request->boolean('no_track')) {
            $clickId = $this->clicks->record($link, $request, $destination, $ua);
        }

        $target = DestinationUrl::build($destination, $request->query(), $link->track_conversion ? $clickId : null);

        return new RedirectDecision(
            $link->rewrite ? RedirectDecision::CLOAK : RedirectDecision::REDIRECT,
            $target,
            $link,
            $variant,
        );
    }

    public function unlockToken(Link $link): string
    {
        // Bound to the current password hash: changing the password invalidates every existing unlock.
        return hash_hmac('sha256', $link->id.'|'.$link->password, (string) config('app.key'));
    }

    public function cookieName(Link $link): string
    {
        return 'sp_'.substr(sha1($link->id), 0, 12);
    }

    public function passwordMatches(Link $link, string $attempt): bool
    {
        return $link->password !== null && Hash::check($attempt, $link->password);
    }

    private function hasUnlocked(Request $request, Link $link): bool
    {
        $cookie = $request->cookies->get($this->cookieName($link));

        return is_string($cookie) && hash_equals($this->unlockToken($link), $cookie);
    }

    private function stickyVariant(Request $request, Link $link): ?int
    {
        $value = $request->cookies->get('ab_'.substr(sha1($link->id), 0, 12));

        return is_numeric($value) ? (int) $value : null;
    }

    private function domainFallback(string $domain, string $column): ?string
    {
        return Domain::query()->where('slug', $domain)->value($column);
    }
}
