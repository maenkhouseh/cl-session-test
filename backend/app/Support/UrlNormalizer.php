<?php

namespace App\Support;

use App\Exceptions\ApiException;

/** Normalises user-supplied destination URLs and answers host questions about them. */
final class UrlNormalizer
{
    /** Adds https:// when the scheme is missing, trims, and rejects non-web or host-less URLs. */
    public static function normalize(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw ApiException::unprocessable('The destination URL is required.');
        }

        if (! preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url)) {
            $url = 'https://'.ltrim($url, '/');
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            throw ApiException::unprocessable('Invalid destination URL: only http(s) URLs with a host are supported.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw ApiException::unprocessable('Invalid destination URL: embedded credentials are not allowed.');
        }

        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?$|^\[[0-9a-f:]+\]$/i', $parts['host'])) {
            throw ApiException::unprocessable('Invalid destination URL: malformed host.');
        }

        return $url;
    }

    public static function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host ? strtolower($host) : null;
    }
}
