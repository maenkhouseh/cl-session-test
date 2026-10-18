<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds the filtered link query once; list, count and analytics scoping all share it.
 * Every filter maps to an index (see the links migration) — no filter forces a table scan inside a workspace.
 */
class LinkQuery
{
    private const SORT_COLUMNS = [
        'createdAt' => 'created_at', 'clicks' => 'clicks', 'saleAmount' => 'sale_amount', 'lastClicked' => 'last_clicked_at',
    ];

    /** @param array<string, mixed> $f validated filter input */
    public function filtered(Workspace $workspace, array $f): Builder
    {
        $query = $workspace->links()->getQuery();

        $query->when(! ($f['showArchived'] ?? false), fn ($q) => $q->where('archived', false))
            ->when($f['domain'] ?? null, fn ($q, $v) => $q->where('domain', strtolower($v)))
            ->when($f['userId'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['tenantId'] ?? null, fn ($q, $v) => $q->where('tenant_id', $v))
            ->when($f['externalId'] ?? null, fn ($q, $v) => $q->where('external_id', $v))
            ->when($f['folderId'] ?? null, fn ($q, $v) => $v === 'none' ? $q->whereNull('folder_id') : $q->where('folder_id', $v))
            ->when($f['search'] ?? null, function ($q, $v) {
                $like = '%'.addcslashes($v, '%_\\').'%';
                $q->where(fn ($q) => $q->where('key', 'ilike', $like)->orWhere('url', 'ilike', $like)->orWhere('title', 'ilike', $like));
            });

        $this->tagFilter($workspace, $query, $f);

        return $query;
    }

    public function sorted(Builder $query, array $f): Builder
    {
        $column = self::SORT_COLUMNS[$f['sortBy'] ?? 'createdAt'];
        $direction = ($f['sortOrder'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        // Never-clicked links sort last in both directions (matches links_workspace_last_clicked_idx).
        return $query->orderByRaw("{$column} {$direction}".($column === 'last_clicked_at' ? ' nulls last' : ''))->orderBy('id', $direction);
    }

    private function tagFilter(Workspace $workspace, Builder $query, array $f): void
    {
        $ids = $f['tagIds'] ?? [];
        $names = array_map('strtolower', $f['tagNames'] ?? []);

        if (! $ids && ! $names) {
            return;
        }

        // Resolve names to ids in one tiny query, then a single EXISTS on the (tag_id, link_id) index.
        if ($names) {
            $ids = array_merge($ids, $workspace->tags()->whereIn(DB::raw('lower(name)'), $names)->pluck('id')->all());
        }

        $query->whereExists(fn ($q) => $q->select(DB::raw(1))->from('link_tag')
            ->whereColumn('link_tag.link_id', 'links.id')->whereIn('link_tag.tag_id', $ids ?: ['__none__']));
    }
}
