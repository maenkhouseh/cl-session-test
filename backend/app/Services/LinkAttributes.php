<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Link;
use App\Models\Workspace;
use App\Support\CaseConverter;
use App\Support\UrlNormalizer;
use App\Support\UtmParams;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

/** Turns validated API input into persistable, normalised, plan-checked link attributes. */
class LinkAttributes
{
    /** Input keys that do not map to a column. */
    private const NON_COLUMN = ['tag_ids', 'tag_names', 'key_length', 'prefix', 'webhook_ids', 'ref_', 'tag_id'];

    public function __construct(private LinkFeatureGate $gate, private DomainRegistry $domains) {}

    /**
     * @param  array<string, mixed>  $input  camelCase API input (only keys the caller sent)
     * @return array<string, mixed> snake_case column values (only keys the caller sent, plus derived UTM fields)
     */
    public function prepare(Workspace $workspace, array $input, ?Link $existing = null): array
    {
        $a = Arr::except(CaseConverter::toSnake($input), self::NON_COLUMN);

        if (array_key_exists('url', $a)) {
            $a['url'] = $this->destination($a['url']);
        }

        $touchesUtm = array_key_exists('url', $a) || array_intersect_key($a, array_flip(UtmParams::KEYS));
        if ($touchesUtm) {
            $url = $a['url'] ?? $existing?->url ?? throw ApiException::unprocessable('The destination URL is required.');
            $explicit = Arr::only($a, UtmParams::KEYS);
            $url = $explicit ? UtmParams::apply($url, $explicit) : $url;
            $parsed = UtmParams::extract($url);
            $a['url'] = $url;
            foreach (UtmParams::KEYS as $key) {
                $a[$key] = $parsed[$key] ?? null;
            }
        }

        foreach (['expired_url', 'ios', 'android'] as $field) {
            if (! empty($a[$field])) {
                $a[$field] = $this->destination($a[$field]);
            }
        }

        if (array_key_exists('password', $a)) {
            $a['password'] = filled($a['password']) ? Hash::make($a['password']) : null;
        }

        if (! empty($a['geo'])) {
            $a['geo'] = $this->geo($a['geo']);
        }

        if (! empty($a['test_variants'])) {
            $a['test_variants'] = $this->variants($a['test_variants']);
            $a['test_started_at'] ??= $existing?->test_started_at ?? now();
        }

        $this->gate->assert($workspace, $a);

        return $a;
    }

    private function destination(string $url): string
    {
        $url = UrlNormalizer::normalize($url);

        if ($this->domains->pointsAtShortDomain((string) UrlNormalizer::host($url))) {
            throw ApiException::unprocessable('The destination URL cannot point to a short link domain (redirect loop).');
        }

        return $url;
    }

    /** @param array<string, string> $geo */
    private function geo(array $geo): array
    {
        $out = [];
        foreach ($geo as $country => $url) {
            if (! preg_match('/^[A-Za-z]{2}$/', (string) $country)) {
                throw ApiException::unprocessable("Invalid geo-targeting country code \"{$country}\".");
            }
            $out[strtoupper($country)] = $this->destination($url);
        }

        return $out;
    }

    /** @param array<int, array{url: string, percentage: int|float}> $variants */
    private function variants(array $variants): array
    {
        if (count($variants) < 2 || count($variants) > 4) {
            throw ApiException::unprocessable('An A/B test needs between 2 and 4 variants.');
        }

        if (round(array_sum(array_column($variants, 'percentage')), 2) !== 100.0) {
            throw ApiException::unprocessable('A/B test variant percentages must add up to 100.');
        }

        return array_map(fn ($v) => ['url' => $this->destination($v['url']), 'percentage' => (int) $v['percentage']], $variants);
    }
}
