<?php

namespace App\Enums;

enum Plan: string
{
    case Free = 'free';
    case Business = 'business';
    case Advanced = 'advanced';
    case Enterprise = 'enterprise';

    /** Quota for a limit key (null = unlimited). */
    public function limit(string $key): ?int
    {
        return config("shortie.plans.{$this->value}.{$key}");
    }

    public function allows(string $feature): bool
    {
        return in_array($feature, config("shortie.plans.{$this->value}.features", []), true);
    }

    /** @return array<string, int|null> */
    public function limits(): array
    {
        return collect(config("shortie.plans.{$this->value}"))->except('features')->all();
    }
}
