<?php

namespace App\Actions\Links;

use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BulkUpdateLinks
{
    public function __construct(private UpdateLink $update) {}

    /** @param array<int, string> $linkIds @param array<string, mixed> $data @return Collection<int, \App\Models\Link> */
    public function __invoke(Workspace $workspace, array $linkIds, array $data): Collection
    {
        $links = $workspace->links()->with('workspace')->whereIn('id', $linkIds)->get();

        return DB::transaction(fn () => $links->map(fn ($link) => ($this->update)($link, $data)));
    }
}
