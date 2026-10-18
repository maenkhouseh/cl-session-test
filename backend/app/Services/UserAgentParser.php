<?php

namespace App\Services;

use DeviceDetector\DeviceDetector;
use Illuminate\Support\Facades\Cache;

class UserAgentParser
{
    private const DEVICES = [
        'desktop' => 'Desktop', 'smartphone' => 'Mobile', 'feature phone' => 'Mobile', 'phablet' => 'Mobile',
        'tablet' => 'Tablet', 'console' => 'Console', 'tv' => 'Smart TV', 'wearable' => 'Wearable',
        'portable media player' => 'Other', 'car browser' => 'Other', 'camera' => 'Other', 'smart display' => 'Smart TV',
    ];

    private const PREVIEW_BOTS = '/facebookexternalhit|twitterbot|linkedinbot|slackbot|discordbot|whatsapp|telegrambot|pinterest|skypeuripreview|redditbot|embedly|vkshare|googlebot|bingbot|applebot|iframely/i';

    /** @return array{device: string, browser: ?string, os: ?string, bot: bool, preview_bot: bool} */
    public function parse(?string $userAgent): array
    {
        $userAgent = mb_substr((string) $userAgent, 0, 512);

        // Parsing is the most expensive part of a click; a UA string repeats constantly, so memoise it.
        return Cache::remember('ua:'.md5($userAgent), 86400, function () use ($userAgent) {
            $detector = new DeviceDetector($userAgent);
            $detector->skipBotDetection(false);
            $detector->parse();

            return [
                'device' => $detector->isBot() ? 'Bot' : (self::DEVICES[$detector->getDeviceName()] ?? 'Desktop'),
                'browser' => $detector->getClient('name') ?: null,
                'os' => $detector->getOs('name') ?: null,
                'bot' => $detector->isBot() || $userAgent === '',
                'preview_bot' => (bool) preg_match(self::PREVIEW_BOTS, $userAgent),
            ];
        });
    }
}
