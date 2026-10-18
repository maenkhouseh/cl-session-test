<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Workspace;

/** Rejects link options that the workspace's plan does not include. */
class LinkFeatureGate
{
    /** attribute (snake_case) => plan feature */
    private const MAP = [
        'password' => 'password',
        'expires_at' => 'expiration',
        'expired_url' => 'expiration',
        'geo' => 'geo_targeting',
        'ios' => 'device_targeting',
        'android' => 'device_targeting',
        'rewrite' => 'cloaking',
        'proxy' => 'custom_preview',
        'title' => 'custom_preview',
        'description' => 'custom_preview',
        'image' => 'custom_preview',
        'video' => 'custom_preview',
        'test_variants' => 'ab_testing',
        'track_conversion' => 'conversion_tracking',
    ];

    /** @param array<string, mixed> $attributes snake_case attributes being written */
    public function assert(Workspace $workspace, array $attributes): void
    {
        foreach (self::MAP as $attribute => $feature) {
            if (! $this->isUsed($attributes[$attribute] ?? null) || $workspace->plan->allows($feature)) {
                continue;
            }

            throw ApiException::forbidden('The "'.$this->label($attribute).'" option is not available on your current plan. Upgrade to use it.');
        }
    }

    private function isUsed(mixed $value): bool
    {
        return ! in_array($value, [null, false, '', []], true);
    }

    private function label(string $attribute): string
    {
        return lcfirst(str_replace('_', ' ', ucwords($attribute, '_')));
    }
}
