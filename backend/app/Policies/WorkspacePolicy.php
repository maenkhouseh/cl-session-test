<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function manage(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user)?->canManageWorkspace() ?? false;
    }
}
