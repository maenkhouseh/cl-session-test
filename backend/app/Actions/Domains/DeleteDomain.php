<?php

namespace App\Actions\Domains;

use App\Exceptions\ApiException;
use App\Models\Domain;

class DeleteDomain
{
    public function __invoke(Domain $domain): void
    {
        if ($domain->workspace->links()->where('domain', $domain->slug)->exists()) {
            throw ApiException::badRequest('Delete or move all links on this domain before removing it.');
        }

        $domain->delete();
    }
}
