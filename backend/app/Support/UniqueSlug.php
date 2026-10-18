<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class UniqueSlug
{
    /** @param class-string<Model> $model */
    public static function for(string $model, string $value, string $column = 'slug'): string
    {
        $base = Str::slug($value) ?: 'workspace';
        $slug = $base;

        for ($i = 2; $model::query()->where($column, $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
