<?php

namespace App\Support;

use Illuminate\Support\Str;

final class CaseConverter
{
    /** Top-level camelCase → snake_case keys (`utm_*` keys are already snake and stay as-is). */
    public static function toSnake(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $out[Str::snake($key)] = $value;
        }

        return $out;
    }
}
