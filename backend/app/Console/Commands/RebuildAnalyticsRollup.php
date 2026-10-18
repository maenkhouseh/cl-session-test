<?php

namespace App\Console\Commands;

use App\Services\Analytics\DailyRollup;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rollup:rebuild {--from= : First UTC day (Y-m-d)} {--to= : Last UTC day (Y-m-d)}')]
#[Description('Recompute the daily analytics roll-up from raw events (backfill / repair)')]
class RebuildAnalyticsRollup extends Command
{
    public function handle(DailyRollup $rollup): int
    {
        $rows = $rollup->rebuild(
            $this->option('from') ? Carbon::parse($this->option('from'), 'UTC') : null,
            $this->option('to') ? Carbon::parse($this->option('to'), 'UTC') : null,
        );

        $this->info("Rebuilt {$rows} daily rows.");

        return self::SUCCESS;
    }
}
