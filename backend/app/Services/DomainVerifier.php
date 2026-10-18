<?php

namespace App\Services;

/** Swappable DNS check so tests (and other environments) can fake verification. */
interface DomainVerifier
{
    public function verify(string $host): bool;
}
