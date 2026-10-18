<?php

namespace Tests\Concerns;

use App\Enums\Plan;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

trait CreatesWorkspaces
{
    /** @return array{0: User, 1: Workspace} owner + their workspace */
    protected function ownerWithWorkspace(Plan $plan = Plan::Business): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->plan($plan)->create(['owner_id' => $user->id]);
        $workspace->users()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return [$user, $workspace];
    }

    protected function memberOf(Workspace $workspace, WorkspaceRole $role = WorkspaceRole::Member): User
    {
        $user = User::factory()->create();
        $workspace->users()->attach($user->id, ['role' => $role->value]);

        return $user;
    }

    /** Act as $user (Sanctum) within $workspace. */
    protected function actingInWorkspace(User $user, Workspace $workspace): static
    {
        return $this->actingAs($user, 'sanctum')->withHeader('X-Workspace', $workspace->slug);
    }
}
