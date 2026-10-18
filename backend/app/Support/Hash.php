<?php

namespace App\Support;

/** Deterministic hashing for high-entropy secrets (API keys, invite tokens) that must be looked up by value. */
final class Hash
{
    public static function secret(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
