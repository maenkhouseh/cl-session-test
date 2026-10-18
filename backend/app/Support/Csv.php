<?php

namespace App\Support;

final class Csv
{
    /** Neutralise spreadsheet formula injection (cells starting with = + - @ are prefixed with a quote). */
    public static function cell(mixed $value): string
    {
        $text = is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? '');

        return $text !== '' && str_contains("=+-@\t\r", $text[0]) ? "'".$text : $text;
    }
}
