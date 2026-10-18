<?php

namespace Tests\Unit;

use App\Services\GeoResolver;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class GeoResolverTest extends TestCase
{
    private function resolve(array $headers): array
    {
        $request = new Request;
        foreach ($headers as $k => $v) {
            $request->headers->set($k, $v);
        }

        return (new GeoResolver)->resolve($request);
    }

    public function test_reads_cloudflare_headers(): void
    {
        $this->assertSame(
            ['country' => 'FR', 'region' => 'IDF', 'city' => 'Paris', 'continent' => 'EU'],
            $this->resolve(['CF-IPCountry' => 'fr', 'CF-Region' => 'IDF', 'CF-IPCity' => 'Paris']),
        );
    }

    public function test_reads_vercel_headers_and_decodes_the_city(): void
    {
        $geo = $this->resolve(['X-Vercel-IP-Country' => 'BR', 'X-Vercel-IP-City' => 'S%C3%A3o%20Paulo']);

        $this->assertSame('São Paulo', $geo['city']);
        $this->assertSame('SA', $geo['continent']);
    }

    public function test_ignores_unknown_and_malformed_countries(): void
    {
        foreach (['XX', 'T1', 'USA', '', '1'] as $bad) {
            $this->assertNull($this->resolve(['CF-IPCountry' => $bad])['country'], $bad);
        }
        $this->assertNull($this->resolve([])['country']);
    }
}
