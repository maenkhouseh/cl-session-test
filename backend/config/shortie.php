<?php

/*
 * Plan catalogue. `null` means unlimited. Mirrors the quotas listed on dub.co/pricing,
 * plus a Free tier. Features are gated per-plan via Plan::allows().
 */
$paidFeatures = [
    'ab_testing', 'password', 'geo_targeting', 'device_targeting', 'cloaking',
    'expiration', 'deep_links', 'custom_preview', 'webhooks', 'conversion_tracking',
];

return [
    'plans' => [
        'free' => [
            'links_per_month' => 25, 'events_per_month' => 1_000, 'retention_days' => 30,
            'folders' => 0, 'domains' => 3, 'users' => 1, 'rate_limit' => 60,
            'features' => [],
        ],
        'business' => [
            'links_per_month' => 10_000, 'events_per_month' => 250_000, 'retention_days' => 365 * 3,
            'folders' => 20, 'domains' => 100, 'users' => 10, 'rate_limit' => 1_200,
            'features' => $paidFeatures,
        ],
        'advanced' => [
            'links_per_month' => 50_000, 'events_per_month' => 1_000_000, 'retention_days' => 365 * 5,
            'folders' => 50, 'domains' => 250, 'users' => 20, 'rate_limit' => 3_000,
            'features' => $paidFeatures,
        ],
        'enterprise' => [
            'links_per_month' => null, 'events_per_month' => null, 'retention_days' => null,
            'folders' => null, 'domains' => null, 'users' => null, 'rate_limit' => 10_000,
            'features' => $paidFeatures,
        ],
    ],

    'api_key_prefix' => 'shrt_',
    'invite_ttl_days' => 14,
    'redirect_cache_ttl' => 3600,
];
