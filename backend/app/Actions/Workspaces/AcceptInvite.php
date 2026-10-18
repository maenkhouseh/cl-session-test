<?php

namespace App\Actions\Workspaces;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvite;
use App\Support\Hash;
use Illuminate\Support\Facades\DB;

class AcceptInvite
{
    public function __invoke(User $user, string $token): Workspace
    {
        $invite = WorkspaceInvite::query()->with('workspace')->where('token_hash', Hash::secret($token))->first()
            ?? throw ApiException::notFound('Invitation not found.');

        if ($invite->isExpired()) {
            throw new ApiException('invite_expired', 'This invitation has expired.', 410);
        }

        if (strcasecmp($invite->email, $user->email) !== 0) {
            throw ApiException::forbidden('This invitation was sent to a different email address.');
        }

        DB::transaction(function () use ($invite, $user) {
            $invite->workspace->users()->syncWithoutDetaching([$user->id => ['role' => $invite->role->value]]);
            $invite->delete();
        });

        return $invite->workspace;
    }
}
