<?php

namespace App\Actions\Workspaces;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Models\Workspace;

class RemoveMember
{
    public function __invoke(Workspace $workspace, User $member): void
    {
        if ($workspace->owner_id === $member->id) {
            throw ApiException::badRequest('The workspace owner cannot be removed.');
        }

        $workspace->users()->detach($member->id);
        $workspace->apiKeys()->where('user_id', $member->id)->delete();
    }
}
