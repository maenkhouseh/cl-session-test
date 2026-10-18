<?php

namespace Tests\Unit;

use App\Services\UserAgentParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserAgentParserTest extends TestCase
{
    public static function agents(): array
    {
        return [
            'chrome mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', 'Desktop', 'Chrome', 'Mac', false],
            'safari iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1', 'Mobile', 'Mobile Safari', 'iOS', false],
            'chrome android' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36', 'Mobile', 'Chrome Mobile', 'Android', false],
            'ipad' => ['Mozilla/5.0 (iPad; CPU OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1', 'Tablet', 'Mobile Safari', 'iPadOS', false],
            'googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'Bot', null, null, true],
        ];
    }

    #[DataProvider('agents')]
    public function test_parses_device_browser_os_and_bots(string $ua, string $device, ?string $browser, ?string $os, bool $bot): void
    {
        $parsed = app(UserAgentParser::class)->parse($ua);

        $this->assertSame($device, $parsed['device']);
        $this->assertSame($bot, $parsed['bot']);
        if ($browser !== null) {
            $this->assertSame($browser, $parsed['browser']);
        }
        if ($os !== null) {
            $this->assertSame($os, $parsed['os']);
        }
    }

    public function test_empty_user_agents_are_treated_as_bots(): void
    {
        $this->assertTrue(app(UserAgentParser::class)->parse(null)['bot']);
        $this->assertTrue(app(UserAgentParser::class)->parse('')['bot']);
    }

    public function test_social_crawlers_are_flagged_as_preview_bots(): void
    {
        foreach (['Twitterbot/1.0', 'facebookexternalhit/1.1', 'Slackbot-LinkExpanding 1.0', 'WhatsApp/2.23', 'LinkedInBot/1.0', 'Discordbot/2.0'] as $ua) {
            $this->assertTrue(app(UserAgentParser::class)->parse($ua)['preview_bot'], $ua);
        }
        $this->assertFalse(app(UserAgentParser::class)->parse('Mozilla/5.0 Chrome/124')['preview_bot']);
    }
}
