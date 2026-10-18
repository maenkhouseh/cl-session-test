<?php

namespace App\Services;

use App\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Builds a click event and hands it to the write-behind buffer. No SQL on the redirect path. */
class ClickRecorder
{
    public function __construct(private ClickBuffer $buffer, private GeoResolver $geo) {}

    /** @param array{device: string, browser: ?string, os: ?string} $ua  @return string the click id (for conversion attribution) */
    public function record(Link $link, Request $request, string $destination, array $ua): string
    {
        $clickId = Str::lower(Str::random(16));
        $geo = $this->geo->resolve($request);
        $referer = $this->referer($request);

        $this->buffer->push([
            'occurred_at' => now()->utc()->format('Y-m-d H:i:s.u'),
            'workspace_id' => $link->workspace_id,
            'link_id' => $link->id,
            'click_id' => $clickId,
            'is_unique' => $this->isUnique($link, $request),
            'url' => $destination,
            ...$geo,
            'device' => $ua['device'],
            'browser' => $ua['browser'],
            'os' => $ua['os'],
            'referer' => $referer['host'],
            'referer_url' => $referer['url'],
            'qr' => $request->query('qr') === '1',
            'utm_source' => $link->utm_source,
            'utm_medium' => $link->utm_medium,
            'utm_campaign' => $link->utm_campaign,
            'utm_term' => $link->utm_term,
            'utm_content' => $link->utm_content,
        ]);

        return $clickId;
    }

    /** One visitor (IP + UA) counts as unique per link for an hour. */
    private function isUnique(Link $link, Request $request): bool
    {
        $visitor = sha1($request->ip().'|'.$request->userAgent());

        return Cache::add("click-seen:{$link->id}:{$visitor}", 1, 3600);
    }

    /** @return array{host: string, url: ?string} */
    private function referer(Request $request): array
    {
        $url = $request->headers->get('referer');
        $host = $url ? parse_url($url, PHP_URL_HOST) : null;

        return $host
            ? ['host' => mb_substr(strtolower($host), 0, 255), 'url' => mb_substr($url, 0, 2048)]
            : ['host' => '(direct)', 'url' => null];
    }
}
