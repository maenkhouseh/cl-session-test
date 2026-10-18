<?php

namespace App\Actions\Workspaces;

use App\Enums\Plan;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;

class CreateWorkspace
{
    public function __invoke(User $owner, string $name, ?string $slug = null): Workspace
    {
        return DB::transaction(function () use ($owner, $name, $slug) {
            $workspace = Workspace::create([
                'name' => $name,
                'slug' => $slug ?: UniqueSlug::for(Workspace::class, $name),
                'plan' => Plan::Free,
                'owner_id' => $owner->id,
                'usage_reset_at' => now()->addMonth(),
            ]);

            $workspace->users()->attach($owner->id, ['role' => WorkspaceRole::Owner->value]);

            return $workspace;
        });
    }
}
