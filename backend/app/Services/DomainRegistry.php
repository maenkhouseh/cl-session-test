<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Domain;
use App\Models\Workspace;

/** Which hosts a workspace may create short links on. */
class DomainRegistry
{
    public function isSystemDomain(string $host): bool
    {
        return in_array(strtolower($host), config('shortie.system_domains'), true);
    }

    /** Primary verified workspace domain, else the platform default. */
    public function defaultFor(Workspace $workspace): string
    {
        return Domain::query()->where('workspace_id', $workspace->id)->where('is_primary', true)->where('verified', true)->value('slug')
            ?? config('shortie.default_domain');
    }

    public function assertUsable(Workspace $workspace, string $domain): string
    {
        $domain = strtolower($domain);

        if ($this->isSystemDomain($domain)) {
            return $domain;
        }

        $owned = Domain::query()->where('workspace_id', $workspace->id)->where('slug', $domain)->first();

        if (! $owned) {
            throw ApiException::unprocessable("Domain \"{$domain}\" does not belong to this workspace.");
        }

        if (! $owned->verified) {
            throw ApiException::unprocessable("Domain \"{$domain}\" has not been verified yet.");
        }

        return $domain;
    }

    /** True when the URL points at one of our own short domains (redirect loop). */
    public function pointsAtShortDomain(string $host): bool
    {
        $host = strtolower($host);

        return $this->isSystemDomain($host) || Domain::query()->where('slug', $host)->where('verified', true)->exists();
    }
}
