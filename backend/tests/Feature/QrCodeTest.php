<?php

namespace Tests\Feature;

use Tests\TestCase;

class QrCodeTest extends TestCase
{
    public function test_renders_an_svg_without_authentication(): void
    {
        $response = $this->get('/api/v1/qr?url='.urlencode('https://shortie.link/abc'))->assertOk();

        $this->assertStringContainsString('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $response->getContent());
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
    }

    public function test_supports_png_and_custom_colours(): void
    {
        $response = $this->get('/api/v1/qr?url='.urlencode('https://shortie.link/abc').'&format=png&fgColor=ff0000&bgColor=fff&size=200&level=H')->assertOk();

        $this->assertStringContainsString('image/png', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }

    public function test_rejects_non_http_urls_and_bad_options(): void
    {
        foreach (['url=javascript:alert(1)', 'url=ftp://x.com', '', 'url=https://a.com&size=5', 'url=https://a.com&fgColor=zzz', 'url=https://a.com&level=X'] as $qs) {
            $this->getJson("/api/v1/qr?{$qs}")->assertStatus(422)->assertJsonPath('error.code', 'unprocessable_entity');
        }
    }
}
