<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/** Atomic quota consumption: the conditional UPDATE makes the limit check race-free. */
class UsageMeter
{
    public function consumeLink(Workspace $workspace): void
    {
        $limit = $workspace->plan->limit('links_per_month');

        $updated = Workspace::query()->whereKey($workspace->id)
            ->when($limit !== null, fn ($q) => $q->where('links_usage', '<', $limit))
            ->update(['links_usage' => DB::raw('links_usage + 1')]);

        if ($updated === 0) {
            throw ApiException::limitExceeded("You've reached your plan's limit of {$limit} new links per month. Upgrade to create more.");
        }

        $workspace->links_usage++;
    }

    public function releaseLinks(Workspace $workspace, int $count = 1): void
    {
        Workspace::query()->whereKey($workspace->id)
            ->update(['links_usage' => DB::raw("GREATEST(links_usage - {$count}, 0)")]);
    }
}
