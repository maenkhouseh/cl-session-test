<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Creates monthly range partitions (plus a DEFAULT safety net) for the event tables. */
class EventPartitions
{
    /** @return array<int, string> names of partitions created in this call */
    public function ensure(string $table, int $monthsAhead = 3, ?CarbonInterface $from = null): array
    {
        $created = [];
        $start = ($from ?? now())->copy()->utc()->startOfMonth()->subMonth();

        DB::statement("CREATE TABLE IF NOT EXISTS {$table}_default PARTITION OF {$table} DEFAULT");

        for ($i = 0; $i <= $monthsAhead + 1; $i++) {
            $month = $start->copy()->addMonths($i);
            $name = "{$table}_".$month->format('Y_m');

            if (! $this->exists($name)) {
                $next = $month->copy()->addMonth();
                DB::statement("CREATE TABLE {$name} PARTITION OF {$table} FOR VALUES FROM ('{$month->toDateString()} 00:00:00+00') TO ('{$next->toDateString()} 00:00:00+00')");
                $created[] = $name;
            }
        }

        return $created;
    }

    private function exists(string $name): bool
    {
        return DB::selectOne('SELECT to_regclass(?) AS t', [$name])->t !== null;
    }
}
