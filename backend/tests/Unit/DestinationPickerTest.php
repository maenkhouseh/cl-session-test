<?php

namespace Tests\Unit;

use App\Models\Link;
use App\Services\DestinationPicker;
use PHPUnit\Framework\TestCase;

class DestinationPickerTest extends TestCase
{
    private function link(array $attrs = []): Link
    {
        return new Link(['url' => 'https://default.com', ...$attrs]);
    }

    private function pick(Link $link, ?string $os = null, ?string $country = null, ?int $sticky = null): array
    {
        return (new DestinationPicker)->pick($link, ['device' => 'Desktop', 'os' => $os], $country, $sticky);
    }

    public function test_default_url_when_nothing_matches(): void
    {
        $this->assertSame(['https://default.com', null], $this->pick($this->link()));
    }

    public function test_device_targeting_beats_geo(): void
    {
        $link = $this->link(['ios' => 'https://ios.com', 'geo' => ['US' => 'https://us.com']]);

        $this->assertSame('https://ios.com', $this->pick($link, 'iOS', 'US')[0]);
        $this->assertSame('https://ios.com', $this->pick($link, 'iPadOS', 'US')[0]);
        $this->assertSame('https://us.com', $this->pick($link, 'Android', 'US')[0]);
    }

    public function test_geo_beats_ab_testing(): void
    {
        $link = $this->link(['geo' => ['US' => 'https://us.com'], 'test_variants' => [['url' => 'https://a.com', 'percentage' => 50], ['url' => 'https://b.com', 'percentage' => 50]]]);

        $this->assertSame('https://us.com', $this->pick($link, null, 'US')[0]);
    }

    public function test_ab_variant_weights_are_respected_statistically(): void
    {
        $link = $this->link(['test_variants' => [['url' => 'https://a.com', 'percentage' => 90], ['url' => 'https://b.com', 'percentage' => 10]]]);

        $a = 0;
        for ($i = 0; $i < 2000; $i++) {
            $a += $this->pick($link)[0] === 'https://a.com' ? 1 : 0;
        }

        $this->assertGreaterThan(1650, $a);
        $this->assertLessThan(1950, $a);
    }

    public function test_sticky_index_wins_when_valid_and_is_ignored_when_stale(): void
    {
        $link = $this->link(['test_variants' => [['url' => 'https://a.com', 'percentage' => 50], ['url' => 'https://b.com', 'percentage' => 50]]]);

        $this->assertSame(['https://b.com', '1'], $this->pick($link, null, null, 1));
        $this->assertContains($this->pick($link, null, null, 9)[0], ['https://a.com', 'https://b.com']);
    }
}
