<?php

namespace App\Services;

use App\Models\Link;

/** Chooses the destination for a visitor: device deep links → geo → A/B variant → default URL. */
class DestinationPicker
{
    /**
     * @param  array{device: string, os: ?string}  $ua
     * @return array{0: string, 1: ?string} [url, sticky variant index to remember (A/B only)]
     */
    public function pick(Link $link, array $ua, ?string $country, ?int $stickyVariant = null): array
    {
        if ($link->ios && in_array($ua['os'] ?? null, ['iOS', 'iPadOS'], true)) {
            return [$link->ios, null];
        }

        if ($link->android && ($ua['os'] ?? null) === 'Android') {
            return [$link->android, null];
        }

        if ($country && ! empty($link->geo[$country])) {
            return [$link->geo[$country], null];
        }

        if ($this->hasActiveTest($link)) {
            $index = $stickyVariant !== null && isset($link->test_variants[$stickyVariant]) ? $stickyVariant : $this->weightedIndex($link->test_variants);

            return [$link->test_variants[$index]['url'], (string) $index];
        }

        return [$link->url, null];
    }

    private function hasActiveTest(Link $link): bool
    {
        return count($link->test_variants ?? []) >= 2 && $link->test_completed_at === null;
    }

    /** @param array<int, array{percentage: int}> $variants */
    private function weightedIndex(array $variants): int
    {
        $roll = random_int(1, 100);
        $cumulative = 0;

        foreach ($variants as $i => $variant) {
            $cumulative += $variant['percentage'];
            if ($roll <= $cumulative) {
                return $i;
            }
        }

        return array_key_last($variants);
    }
}
