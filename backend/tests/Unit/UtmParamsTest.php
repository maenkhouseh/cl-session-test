<?php

namespace Tests\Unit;

use App\Support\UtmParams;
use PHPUnit\Framework\TestCase;

class UtmParamsTest extends TestCase
{
    public function test_extracts_only_tracked_params(): void
    {
        $this->assertSame(
            ['utm_source' => 'g', 'ref' => 'x'],
            UtmParams::extract('https://a.com/?utm_source=g&other=1&ref=x&utm_term='),
        );
    }

    public function test_apply_adds_updates_and_removes(): void
    {
        $url = 'https://a.com/p?keep=1&utm_source=old#frag';

        $this->assertSame(
            'https://a.com/p?keep=1&utm_source=new&utm_medium=cpc#frag',
            UtmParams::apply($url, ['utm_source' => 'new', 'utm_medium' => 'cpc']),
        );
        $this->assertSame('https://a.com/p?keep=1#frag', UtmParams::apply($url, ['utm_source' => null]));
    }

    public function test_apply_ignores_untracked_keys_and_preserves_port_and_path(): void
    {
        $this->assertSame('https://a.com:8443/x/y', UtmParams::apply('https://a.com:8443/x/y', ['foo' => 'bar']));
    }

    public function test_values_are_url_encoded(): void
    {
        $this->assertSame('https://a.com?utm_campaign=summer%20sale', UtmParams::apply('https://a.com', ['utm_campaign' => 'summer sale']));
    }
}
