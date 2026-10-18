<?php

namespace App\Support;

/** Keeps `utm_*`/`ref` fields and the destination URL's query string in sync (both directions). */
final class UtmParams
{
    public const KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ref'];

    /** Parameters found on the URL, restricted to the tracked keys. @return array<string, string> */
    public static function extract(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return collect($query)->only(self::KEYS)->filter(fn ($v) => is_string($v) && $v !== '')->all();
    }

    /**
     * Writes the given params onto the URL; a null/empty value removes that param.
     *
     * @param  array<string, ?string>  $params
     */
    public static function apply(string $url, array $params): string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        foreach (collect($params)->only(self::KEYS) as $key => $value) {
            if ($value === null || $value === '') {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }

        $rebuilt = ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '');
        if ($query) {
            $rebuilt .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $rebuilt.(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
