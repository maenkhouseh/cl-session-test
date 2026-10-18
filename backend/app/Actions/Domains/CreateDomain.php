<?php

namespace App\Actions\Domains;

use App\Exceptions\ApiException;
use App\Models\Domain;
use App\Models\Workspace;
use App\Services\DomainRegistry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateDomain
{
    public function __construct(private DomainRegistry $registry) {}

    public function __invoke(Workspace $workspace, string $slug, array $options = []): Domain
    {
        $slug = strtolower(trim($slug));

        if ($this->registry->isSystemDomain($slug)) {
            throw ApiException::conflict('This domain is reserved by the platform.');
        }

        $limit = $workspace->plan->limit('domains');
        if ($limit !== null && $workspace->domains()->count() >= $limit) {
            throw ApiException::limitExceeded("Your plan allows up to {$limit} custom domains.");
        }

        try {
            return DB::transaction(fn () => $workspace->domains()->create([
                ...$options,
                'slug' => $slug,
                // The first domain a workspace adds becomes its primary one.
                'is_primary' => ! $workspace->domains()->exists(),
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ApiException::conflict('This domain is already in use.');
        }
    }
}
