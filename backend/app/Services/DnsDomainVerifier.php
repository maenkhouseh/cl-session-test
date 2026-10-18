<?php

namespace App\Services;

/** A domain is verified once it resolves (CNAME/A) to our configured redirect target. */
class DnsDomainVerifier implements DomainVerifier
{
    public function verify(string $host): bool
    {
        $target = config('shortie.dns_target');

        foreach (dns_get_record($host, DNS_CNAME | DNS_A) ?: [] as $record) {
            if (($record['target'] ?? $record['ip'] ?? null) === $target) {
                return true;
            }
        }

        return false;
    }
}
