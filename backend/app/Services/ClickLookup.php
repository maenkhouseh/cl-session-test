<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/** Finds the click a conversion is attributed to (bounded to the attribution window). */
class ClickLookup
{
    public const WINDOW_DAYS = 90;

    private const DIMENSIONS = [
        'url', 'country', 'region', 'city', 'continent', 'device', 'browser', 'os', 'referer', 'referer_url', 'qr',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    ];

    public function find(Workspace $workspace, string $clickId): object
    {
        return DB::table('click_events')
            ->where('click_id', $clickId)
            ->where('workspace_id', $workspace->id)
            ->where('occurred_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->orderBy('occurred_at')
            ->first(['link_id', 'click_id', ...self::DIMENSIONS])
            ?? throw ApiException::notFound('Click event not found. It may be outside the attribution window or not processed yet.');
    }

    /** @return array<string, mixed> the dimension columns of a click, ready to merge into a conversion row */
    public function dimensions(object $click): array
    {
        return collect(self::DIMENSIONS)->mapWithKeys(fn ($c) => [$c => $click->$c ?? null])->all();
    }
}
