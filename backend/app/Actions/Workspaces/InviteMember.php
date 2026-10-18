<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Exceptions\ApiException;
use App\Models\Workspace;
use App\Models\WorkspaceInvite;
use App\Notifications\WorkspaceInvitation;
use App\Support\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InviteMember
{
    /** @return array{0: WorkspaceInvite, 1: string} invite and the plain token (shown/mailed once) */
    public function __invoke(Workspace $workspace, string $email, WorkspaceRole $role = WorkspaceRole::Member): array
    {
        $email = Str::lower($email);

        if ($workspace->users()->where('users.email', $email)->exists()) {
            throw ApiException::conflict('This user is already a member of the workspace.');
        }

        $limit = $workspace->plan->limit('users');
        $isNewInvite = ! $workspace->invites()->where('email', $email)->exists();

        if ($limit !== null && $isNewInvite && $workspace->users()->count() + $workspace->invites()->count() >= $limit) {
            throw ApiException::limitExceeded("Your plan allows up to {$limit} users. Upgrade to invite more.");
        }

        $token = Str::random(48);

        $invite = $workspace->invites()->updateOrCreate(
            ['email' => $email],
            [
                'role' => $role,
                'token_hash' => Hash::secret($token),
                'expires_at' => now()->addDays(config('shortie.invite_ttl_days')),
            ],
        );

        Notification::route('mail', $email)->notify(new WorkspaceInvitation($workspace, $token));

        return [$invite, $token];
    }
}
