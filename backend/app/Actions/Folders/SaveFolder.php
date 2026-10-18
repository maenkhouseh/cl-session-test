<?php

namespace App\Actions\Folders;

use App\Exceptions\ApiException;
use App\Models\Folder;
use App\Models\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SaveFolder
{
    /** @param array{name?: string, description?: ?string, access_level?: string} $data */
    public function __invoke(Workspace $workspace, array $data, ?Folder $folder = null): Folder
    {
        if (! $folder) {
            $limit = $workspace->plan->limit('folders');
            if ($limit !== null && $workspace->folders()->count() >= $limit) {
                throw ApiException::limitExceeded($limit === 0
                    ? 'Folders are not available on your plan. Upgrade to organise links into folders.'
                    : "Your plan allows up to {$limit} folders.");
            }
        }

        try {
            return DB::transaction(function () use ($workspace, $data, $folder) {
                if ($folder) {
                    $folder->update($data);

                    return $folder;
                }

                return $workspace->folders()->create($data);
            });
        } catch (UniqueConstraintViolationException) {
            throw ApiException::conflict('A folder with this name already exists.');
        }
    }
}
