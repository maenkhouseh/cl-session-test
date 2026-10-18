<?php

namespace App\Services;

use App\Support\Continents;
use Illuminate\Http\Request;

/** Reads visitor geography from the CDN/edge headers (Cloudflare, Vercel, or a generic proxy). */
class GeoResolver
{
    /** @return array{country: ?string, region: ?string, city: ?string, continent: ?string} */
    public function resolve(Request $request): array
    {
        $country = $this->header($request, ['CF-IPCountry', 'X-Vercel-IP-Country', 'X-Country-Code']);
        $country = $country && preg_match('/^[A-Za-z]{2}$/', $country) && ! in_array(strtoupper($country), ['XX', 'T1'], true) ? strtoupper($country) : null;

        return [
            'country' => $country,
            'region' => $this->header($request, ['CF-Region', 'X-Vercel-IP-Country-Region']),
            'city' => $this->decode($this->header($request, ['CF-IPCity', 'X-Vercel-IP-City'])),
            'continent' => Continents::for($country),
        ];
    }

    private function header(Request $request, array $names): ?string
    {
        foreach ($names as $name) {
            if (filled($value = $request->header($name))) {
                return $value;
            }
        }

        return null;
    }

    private function decode(?string $value): ?string
    {
        return $value === null ? null : mb_substr(urldecode($value), 0, 120);
    }
}
