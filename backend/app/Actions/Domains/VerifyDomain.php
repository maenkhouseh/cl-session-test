<?php

namespace App\Actions\Domains;

use App\Models\Domain;
use App\Services\DomainVerifier;

class VerifyDomain
{
    public function __construct(private DomainVerifier $verifier) {}

    public function __invoke(Domain $domain): Domain
    {
        $verified = $this->verifier->verify($domain->slug);

        if ($verified !== $domain->verified) {
            $domain->update(['verified' => $verified, 'verified_at' => $verified ? now() : null]);
        }

        return $domain;
    }
}
