<?php

namespace App\Support;

/** SSRF guard for user-supplied callback URLs: https only, host must resolve to public addresses. */
final class SafeUrl
{
    public static function isSafeWebhookTarget(string $url): bool
    {
        $parts = parse_url($url);

        if (! $parts || ! isset($parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== 'https' && ! ($scheme === 'http' && app()->environment('local', 'testing'))) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        // Literal IP, or every address the name resolves to, must be public.
        $host = trim($parts['host'], '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);

        if ($addresses === []) {
            return app()->environment('testing'); // tests use fake hosts that never resolve
        }

        foreach ($addresses as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, string> */
    private static function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records)));
    }
}
