<?php

namespace App\Support;

use App\Models\ApiKey;
use App\Models\User;
use App\Models\Workspace;

/** Request-scoped holder for the workspace (and credential) the current request acts on. */
class CurrentWorkspace
{
    public ?Workspace $workspace = null;

    public ?User $user = null;

    public ?ApiKey $apiKey = null;

    public function set(Workspace $workspace, User $user, ?ApiKey $apiKey = null): void
    {
        [$this->workspace, $this->user, $this->apiKey] = [$workspace, $user, $apiKey];
    }

    public function workspace(): Workspace
    {
        return $this->workspace ?? throw new \LogicException('No workspace resolved for this request.');
    }
}
