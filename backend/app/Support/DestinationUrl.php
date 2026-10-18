<?php

namespace App\Support;

/** Builds the final redirect target: destination query + passthrough visitor params (+ conversion click id). */
final class DestinationUrl
{
    /** Params meant for us, never forwarded to the destination. */
    private const INTERNAL = ['qr', 'no_track'];

    /** @param array<string, mixed> $visitorQuery */
    public static function build(string $destination, array $visitorQuery, ?string $clickId = null, string $clickParam = 'shortie_id'): string
    {
        $forward = array_diff_key($visitorQuery, array_flip(self::INTERNAL));

        if ($clickId !== null) {
            $forward[$clickParam] = $clickId;
        }

        if (! $forward) {
            return $destination;
        }

        $fragment = '';
        if (($pos = strpos($destination, '#')) !== false) {
            $fragment = substr($destination, $pos);
            $destination = substr($destination, 0, $pos);
        }

        parse_str((string) parse_url($destination, PHP_URL_QUERY), $existing);
        $base = explode('?', $destination, 2)[0];

        // Destination's own params come first and win over visitor-supplied ones with the same name.
        $merged = $existing + $forward;

        return $base.'?'.http_build_query($merged, '', '&', PHP_QUERY_RFC3986).$fragment;
    }
}
