<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Maintains analytics_daily: incremental upserts on the write path, full rebuild for backfill/repair. */
class DailyRollup
{
    private const COLUMNS = ['clicks', 'unique_clicks', 'leads', 'sales', 'sale_amount'];

    /**
     * Add deltas, one row per (link, UTC day).
     *
     * @param  array<int, array{workspace_id: string, link_id: string, day: string, clicks?: int, unique_clicks?: int, leads?: int, sales?: int, sale_amount?: int}>  $rows
     */
    public function increment(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        // Merge duplicates so a single INSERT never touches the same key twice (ON CONFLICT would reject it).
        $merged = [];
        foreach ($rows as $row) {
            $key = $row['link_id'].'|'.$row['day'];
            $merged[$key] ??= ['workspace_id' => $row['workspace_id'], 'link_id' => $row['link_id'], 'day' => $row['day'], ...array_fill_keys(self::COLUMNS, 0)];
            foreach (self::COLUMNS as $column) {
                $merged[$key][$column] += (int) ($row[$column] ?? 0);
            }
        }

        foreach (array_chunk(array_values($merged), 500) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '(?, ?, ?::date, ?, ?, ?, ?, ?)'));
            $bindings = [];
            foreach ($chunk as $row) {
                array_push($bindings, $row['workspace_id'], $row['link_id'], $row['day'], ...array_map(fn ($c) => $row[$c], self::COLUMNS));
            }

            DB::insert("INSERT INTO analytics_daily (workspace_id, link_id, day, clicks, unique_clicks, leads, sales, sale_amount)
                VALUES {$placeholders}
                ON CONFLICT (link_id, day) DO UPDATE SET
                    clicks = analytics_daily.clicks + EXCLUDED.clicks,
                    unique_clicks = analytics_daily.unique_clicks + EXCLUDED.unique_clicks,
                    leads = analytics_daily.leads + EXCLUDED.leads,
                    sales = analytics_daily.sales + EXCLUDED.sales,
                    sale_amount = analytics_daily.sale_amount + EXCLUDED.sale_amount", $bindings);
        }
    }

    /** Convenience for single conversion events. */
    public function recordEvent(string $workspaceId, string $linkId, CarbonInterface|string $at, array $delta): void
    {
        $day = ($at instanceof CarbonInterface ? $at->copy() : Carbon::parse($at))->utc()->toDateString();

        $this->increment([['workspace_id' => $workspaceId, 'link_id' => $linkId, 'day' => $day, ...$delta]]);
    }

    /** Recompute the given UTC day range [from, to] from the raw event tables. @return int rows written */
    public function rebuild(?CarbonInterface $from = null, ?CarbonInterface $to = null): int
    {
        $from = ($from ?? Carbon::create(2000, 1, 1, 0, 0, 0, 'UTC'))->copy()->utc()->startOfDay();
        $to = ($to ?? now())->copy()->utc()->endOfDay();

        return DB::transaction(function () use ($from, $to) {
            DB::delete('DELETE FROM analytics_daily WHERE day >= ?::date AND day <= ?::date', [$from->toDateString(), $to->toDateString()]);

            $window = [$from->format('Y-m-d H:i:s.uP'), $to->format('Y-m-d H:i:s.uP')];

            return DB::affectingStatement("INSERT INTO analytics_daily (workspace_id, link_id, day, clicks, unique_clicks, leads, sales, sale_amount)
                SELECT workspace_id, link_id, day, sum(clicks), sum(unique_clicks), sum(leads), sum(sales), sum(sale_amount) FROM (
                    SELECT workspace_id, link_id, (occurred_at AT TIME ZONE 'UTC')::date AS day, count(*) AS clicks, count(*) FILTER (WHERE is_unique) AS unique_clicks, 0::bigint AS leads, 0::bigint AS sales, 0::bigint AS sale_amount
                      FROM click_events WHERE occurred_at >= ? AND occurred_at <= ? GROUP BY 1, 2, 3
                    UNION ALL
                    SELECT workspace_id, link_id, (occurred_at AT TIME ZONE 'UTC')::date, 0, 0, count(*), 0, 0
                      FROM lead_events WHERE occurred_at >= ? AND occurred_at <= ? GROUP BY 1, 2, 3
                    UNION ALL
                    SELECT workspace_id, link_id, (occurred_at AT TIME ZONE 'UTC')::date, 0, 0, 0, count(*), coalesce(sum(amount), 0)
                      FROM sale_events WHERE occurred_at >= ? AND occurred_at <= ? GROUP BY 1, 2, 3
                ) t GROUP BY 1, 2, 3", [...$window, ...$window, ...$window]);
        });
    }
}
