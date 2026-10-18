<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Link;

class KeyGenerator
{
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private const BATCH = 8;

    /** A key that is syntactically valid, not reserved and not taken on the domain. */
    public function generate(string $domain, int $length = 7, string $prefix = ''): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            // One query vets a whole batch of candidates instead of one query per candidate.
            $candidates = collect(range(1, self::BATCH))->map(fn () => $prefix.$this->random($length))->unique();
            $taken = Link::query()->where('domain', $domain)
                ->whereIn(\DB::raw('lower(key)'), $candidates->map(fn ($k) => strtolower($k))->all())
                ->pluck('key')->map(fn ($k) => strtolower($k));

            $free = $candidates->first(fn ($k) => ! $taken->contains(strtolower($k)) && ! $this->isReserved($k));

            if ($free !== null) {
                return $free;
            }
        }

        throw ApiException::conflict('Could not generate a unique short link key. Try a larger keyLength.');
    }

    public function isReserved(string $key): bool
    {
        return in_array(strtolower($key), config('shortie.reserved_keys'), true);
    }

    public function isValid(string $key): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9_\-\/]{0,188}[A-Za-z0-9_\-])?$/', $key);
    }

    private function random(int $length): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
