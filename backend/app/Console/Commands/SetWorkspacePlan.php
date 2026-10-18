<?php

namespace App\Console\Commands;

use App\Enums\Plan;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('workspace:set-plan {slug} {plan : free|business|advanced|enterprise}')]
#[Description('Operator tool: change a workspace\'s plan (there is no self-serve billing integration)')]
class SetWorkspacePlan extends Command
{
    public function handle(): int
    {
        $plan = Plan::tryFrom((string) $this->argument('plan'));

        if (! $plan) {
            $this->error('Unknown plan. Use: '.implode(', ', array_column(Plan::cases(), 'value')));

            return self::FAILURE;
        }

        $updated = Workspace::query()->where('slug', $this->argument('slug'))->update(['plan' => $plan]);

        if (! $updated) {
            $this->error('Workspace not found.');

            return self::FAILURE;
        }

        $this->info("Workspace {$this->argument('slug')} is now on the {$plan->value} plan.");

        return self::SUCCESS;
    }
}
