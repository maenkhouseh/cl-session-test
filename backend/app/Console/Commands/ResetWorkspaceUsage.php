<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('usage:reset')]
#[Description('Reset monthly link/event usage for workspaces whose billing cycle has rolled over')]
class ResetWorkspaceUsage extends Command
{
    public function handle(): int
    {
        $count = 0;

        Workspace::query()->where('usage_reset_at', '<=', now())->chunkById(200, function ($workspaces) use (&$count) {
            foreach ($workspaces as $workspace) {
                // Anchor to the original day-of-month (Jan 31 → Feb 28 → Mar 31), never drift by chaining.
                $anchor = $workspace->usage_reset_at;
                for ($months = 1; ($next = $anchor->copy()->addMonthsNoOverflow($months))->isPast(); $months++);

                $workspace->update(['links_usage' => 0, 'events_usage' => 0, 'usage_reset_at' => $next]);
                $count++;
            }
        });

        $this->info("Reset usage for {$count} workspace(s).");

        return self::SUCCESS;
    }
}
