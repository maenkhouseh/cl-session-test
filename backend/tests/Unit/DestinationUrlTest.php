<?php

namespace Tests\Unit;

use App\Support\DestinationUrl;
use PHPUnit\Framework\TestCase;

class DestinationUrlTest extends TestCase
{
    public function test_returns_the_destination_untouched_without_params(): void
    {
        $this->assertSame('https://a.com/p?x=1#top', DestinationUrl::build('https://a.com/p?x=1#top', []));
    }

    public function test_appends_visitor_params_and_keeps_the_fragment_last(): void
    {
        $this->assertSame('https://a.com/p?x=1&y=2#top', DestinationUrl::build('https://a.com/p?x=1#top', ['y' => '2']));
    }

    public function test_destination_params_override_visitor_params(): void
    {
        $this->assertSame('https://a.com/?plan=pro', DestinationUrl::build('https://a.com/?plan=pro', ['plan' => 'free']));
    }

    public function test_internal_params_are_stripped(): void
    {
        $this->assertSame('https://a.com/', DestinationUrl::build('https://a.com/', ['qr' => '1', 'no_track' => '1']));
    }

    public function test_click_id_is_added_under_the_configured_name(): void
    {
        $this->assertSame('https://a.com/?shortie_id=abc123', DestinationUrl::build('https://a.com/', [], 'abc123'));
        $this->assertSame('https://a.com/?cid=abc123', DestinationUrl::build('https://a.com/', [], 'abc123', 'cid'));
    }

    public function test_array_params_are_encoded(): void
    {
        $this->assertSame('https://a.com/?t%5B0%5D=a&t%5B1%5D=b', DestinationUrl::build('https://a.com/', ['t' => ['a', 'b']]));
    }
}
