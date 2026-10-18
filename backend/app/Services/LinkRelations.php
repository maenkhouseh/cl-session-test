<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/** Resolves tag/folder references from user input, always scoped to the workspace. */
class LinkRelations
{
    /**
     * @param  ?array<int, string>  $ids
     * @param  ?array<int, string>  $names
     * @return ?array<int, string> tag ids to sync, or null when the caller specified neither (leave untouched)
     */
    public function tagIds(Workspace $workspace, ?array $ids, ?array $names): ?array
    {
        if ($ids === null && $names === null) {
            return null;
        }

        $ids = array_values(array_unique($ids ?? []));
        $names = array_values(array_unique(array_map('strtolower', $names ?? [])));

        if (! $ids && ! $names) {
            return [];
        }

        $tags = $workspace->tags()
            ->where(function ($q) use ($ids, $names) {
                $q->whereIn('id', $ids)->orWhereIn(DB::raw('lower(name)'), $names);
            })->get(['id', 'name']);

        $missingIds = array_diff($ids, $tags->pluck('id')->all());
        $missingNames = array_diff($names, $tags->pluck('name')->map(fn ($n) => strtolower($n))->all());

        if ($missingIds || $missingNames) {
            throw ApiException::unprocessable('Tag not found: '.implode(', ', [...$missingIds, ...$missingNames]).'.');
        }

        return $tags->pluck('id')->all();
    }

    public function folderId(Workspace $workspace, ?string $folderId): ?string
    {
        if ($folderId === null || $folderId === '') {
            return null;
        }

        $folder = $workspace->folders()->whereKey($folderId)->first()
            ?? throw ApiException::unprocessable('Folder not found.');

        if ($folder->access_level === 'none' || $folder->access_level === 'read') {
            throw ApiException::forbidden("You don't have write access to the folder \"{$folder->name}\".");
        }

        return $folder->id;
    }
}
