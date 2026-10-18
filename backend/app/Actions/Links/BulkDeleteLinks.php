<?php

namespace App\Actions\Links;

use App\Models\Workspace;
use App\Services\LinkCache;

class BulkDeleteLinks
{
    public function __construct(private LinkCache $cache) {}

    /** @param array<int, string> $linkIds @return int deleted count */
    public function __invoke(Workspace $workspace, array $linkIds): int
    {
        $links = $workspace->links()->whereIn('id', $linkIds)->get(['id', 'domain', 'key']);
        $workspace->links()->whereIn('id', $links->pluck('id'))->delete();
        $links->each(fn ($l) => $this->cache->forget($l->domain, $l->key));

        return $links->count();
    }
}
