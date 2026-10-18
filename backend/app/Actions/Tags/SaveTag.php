<?php

namespace App\Actions\Tags;

use App\Exceptions\ApiException;
use App\Models\Tag;
use App\Models\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SaveTag
{
    /** @param array{name?: string, color?: string} $data */
    public function __invoke(Workspace $workspace, array $data, ?Tag $tag = null): Tag
    {
        try {
            return DB::transaction(function () use ($workspace, $data, $tag) {
                if ($tag) {
                    $tag->update($data);

                    return $tag;
                }

                return $workspace->tags()->create($data);
            });
        } catch (UniqueConstraintViolationException) {
            throw ApiException::conflict('A tag with this name already exists.');
        }
    }
}
