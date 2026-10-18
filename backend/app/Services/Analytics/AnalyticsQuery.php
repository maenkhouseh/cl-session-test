<?php

namespace App\Services\Analytics;

use App\Data\AnalyticsFilters;
use App\Models\Link;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One filter pipeline, three read shapes (totals, timeseries, top-N). Every query is
 * "workspace + time window [+ link scope] [+ dimension filters]" so it is served by the
 * (workspace_id, occurred_at) / (link_id, occurred_at) indexes and pruned to the relevant partitions.
 */
class AnalyticsQuery
{
    /** Microsecond precision: truncating to seconds would drop events from the current second. */
    private const TS = 'Y-m-d H:i:s.uP';

    public const TABLES = ['clicks' => 'click_events', 'leads' => 'lead_events', 'sales' => 'sale_events'];

    /** groupBy value => SQL expression, for plain dimension rankings. */
    private const GROUPS = [
        'country' => 'country', 'region' => 'region', 'city' => 'city', 'continent' => 'continent', 'device' => 'device',
        'browser' => 'browser', 'os' => 'os', 'referer' => 'referer', 'refererUrl' => 'referer_url', 'top_urls' => 'url',
        'utm_source' => 'utm_source', 'utm_medium' => 'utm_medium', 'utm_campaign' => 'utm_campaign',
        'utm_term' => 'utm_term', 'utm_content' => 'utm_content',
        'trigger' => "CASE WHEN qr THEN 'qr' ELSE 'link' END",
    ];

    public const GROUP_BYS = ['count', 'timeseries', 'top_links', ...[
        'country', 'region', 'city', 'continent', 'device', 'browser', 'os', 'referer', 'refererUrl', 'top_urls',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'trigger',
    ]];

    /** Scoped, filtered query over one event table. Returns null when the link scope can match nothing. */
    public function scoped(Workspace $workspace, AnalyticsFilters $f, string $event): ?Builder
    {
        $table = self::TABLES[$event];
        $q = DB::table($table)->where("{$table}.workspace_id", $workspace->id)
            ->where("{$table}.occurred_at", '>=', $f->start->format(self::TS))->where("{$table}.occurred_at", '<=', $f->end->format(self::TS));

        if (! $this->scopeToLinks($q, $table, $workspace, $f)) {
            return null;
        }

        foreach ($f->dimensions as $column => $filter) {
            $filter['negate']
                ? $q->where(fn ($w) => $w->whereNotIn("{$table}.{$column}", $filter['values'])->orWhereNull("{$table}.{$column}"))
                : $q->whereIn("{$table}.{$column}", $filter['values']);
        }

        if ($f->trigger) {
            $q->where("{$table}.qr", $f->trigger === 'qr');
        }

        if ($f->uniqueOnly && $event === 'clicks') {
            $q->where("{$table}.is_unique", true);
        }

        return $q;
    }

    /** Roll-up rows for whole UTC days in [$from, $to). Only valid for link-level filters. */
    private function rollupScoped(Workspace $workspace, AnalyticsFilters $f, CarbonImmutable $from, CarbonImmutable $to): ?Builder
    {
        $q = DB::table('analytics_daily')->where('analytics_daily.workspace_id', $workspace->id)
            ->where('analytics_daily.day', '>=', $from->toDateString())->where('analytics_daily.day', '<', $to->toDateString());

        return $this->scopeToLinks($q, 'analytics_daily', $workspace, $f) ? $q : null;
    }

    /** Link / tag / folder predicates shared by events and the roll-up. False = nothing can match. */
    private function scopeToLinks(Builder $q, string $table, Workspace $workspace, AnalyticsFilters $f): bool
    {
        if ($f->domain && $f->key && ! $f->linkId) {
            $linkId = Link::query()->where('workspace_id', $workspace->id)->forDomainKey($f->domain, $f->key)->value('id');
            if (! $linkId) {
                return false;
            }
            $q->where("{$table}.link_id", $linkId);
        } elseif ($f->linkId) {
            $q->where("{$table}.link_id", $f->linkId);
        }

        if ($f->tagIds) {
            $q->whereExists(fn ($s) => $s->select(DB::raw(1))->from('link_tag')
                ->whereColumn('link_tag.link_id', "{$table}.link_id")->whereIn('link_tag.tag_id', $f->tagIds));
        }

        if ($f->folderId) {
            $q->whereIn("{$table}.link_id", fn ($s) => $s->select('id')->from('links')
                ->where('workspace_id', $workspace->id)->where('folder_id', $f->folderId));
        }

        return true;
    }

    /**
     * Splits a range into [roll-up for whole UTC days] + [raw events for the partial edge days].
     * Falls back to a single raw segment whenever the roll-up cannot answer exactly.
     *
     * @return array<int, array{0: 'raw', 1: AnalyticsFilters}|array{0: 'rollup', 1: CarbonImmutable, 2: CarbonImmutable}>
     */
    private function segments(AnalyticsFilters $f, bool $needsUtcBuckets): array
    {
        if (! config('shortie.analytics_rollup', true) || ! $f->isLinkLevelOnly() || ($needsUtcBuckets && $f->timezone !== 'UTC')) {
            return [['raw', $f]];
        }

        $fullStart = $f->start->equalTo($f->start->startOfDay()) ? $f->start : $f->start->startOfDay()->addDay();
        $fullEnd = $f->end->startOfDay(); // exclusive: the (partial) day containing "now" always comes from raw events

        if ($fullStart >= $fullEnd) {
            return [['raw', $f]];
        }

        $segments = [['rollup', $fullStart, $fullEnd]];
        if ($f->start < $fullStart) {
            $segments[] = ['raw', $f->withWindow($f->start, $fullStart->subMicroseconds(1))];
        }
        $segments[] = ['raw', $f->withWindow($fullEnd, $f->end)];

        return $segments;
    }

    /** @return array{clicks: int, uniqueClicks: int, leads: int, sales: int, saleAmount: int} */
    public function totals(Workspace $workspace, AnalyticsFilters $f): array
    {
        $total = ['clicks' => 0, 'uniqueClicks' => 0, 'leads' => 0, 'sales' => 0, 'saleAmount' => 0];

        foreach ($this->segments($f, needsUtcBuckets: false) as $segment) {
            $part = $segment[0] === 'raw' ? $this->rawTotals($workspace, $segment[1]) : $this->rollupTotals($workspace, $f, $segment[1], $segment[2]);
            foreach ($total as $metric => $value) {
                $total[$metric] = $value + $part[$metric];
            }
        }

        return $total;
    }

    private function rawTotals(Workspace $workspace, AnalyticsFilters $f): array
    {
        $empty = ['clicks' => 0, 'uniqueClicks' => 0, 'leads' => 0, 'sales' => 0, 'saleAmount' => 0];
        $parts = [];

        foreach (['clicks' => 'count(*) as n, count(*) filter (where is_unique) as u, 0::bigint as amount',
            'leads' => 'count(*) as n, 0::bigint as u, 0::bigint as amount',
            'sales' => 'count(*) as n, 0::bigint as u, coalesce(sum(amount), 0)::bigint as amount'] as $event => $select) {
            if ($q = $this->scoped($workspace, $f, $event)) {
                $parts[] = $q->selectRaw("'{$event}' as event, {$select}");
            }
        }

        if (! $parts) {
            return $empty;
        }

        // A single UNION ALL: one round trip, three index-range aggregates.
        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        foreach ($union->get() as $row) {
            match ($row->event) {
                'clicks' => [$empty['clicks'], $empty['uniqueClicks']] = [(int) $row->n, (int) $row->u],
                'leads' => $empty['leads'] = (int) $row->n,
                'sales' => [$empty['sales'], $empty['saleAmount']] = [(int) $row->n, (int) $row->amount],
            };
        }

        return $empty;
    }

    private function rollupTotals(Workspace $workspace, AnalyticsFilters $f, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $q = $this->rollupScoped($workspace, $f, $from, $to);
        $row = $q?->selectRaw('coalesce(sum(clicks), 0) as c, coalesce(sum(unique_clicks), 0) as u, coalesce(sum(leads), 0) as l, coalesce(sum(sales), 0) as s, coalesce(sum(sale_amount), 0) as a')->first();

        return $row
            ? ['clicks' => (int) $row->c, 'uniqueClicks' => (int) $row->u, 'leads' => (int) $row->l, 'sales' => (int) $row->s, 'saleAmount' => (int) $row->a]
            : ['clicks' => 0, 'uniqueClicks' => 0, 'leads' => 0, 'sales' => 0, 'saleAmount' => 0];
    }

    /** @return array<int, array<string, mixed>> */
    public function timeseries(Workspace $workspace, AnalyticsFilters $f): array
    {
        $unit = $f->granularity();
        $buckets = $this->emptyBuckets($f, $unit);

        // Hourly charts cover ≤ 48h, which is cheap to read raw; day/month buckets use the roll-up.
        $segments = $unit === 'hour' ? [['raw', $f]] : $this->segments($f, needsUtcBuckets: true);

        foreach ($segments as $segment) {
            $rows = $segment[0] === 'rollup'
                ? $this->rollupSeries($workspace, $f, $unit, $segment[1], $segment[2])
                : $this->rawSeries($workspace, $segment[1], $unit);

            foreach ($rows as $key => $values) {
                if (! isset($buckets[$key])) {
                    continue;
                }
                foreach ($values as $metric => $value) {
                    $buckets[$key][$metric] += $value;
                }
            }
        }

        return array_values($buckets);
    }

    /** @return array<string, array<string, int>> bucket key => metrics */
    private function rawSeries(Workspace $workspace, AnalyticsFilters $f, string $unit): array
    {
        $q = $this->scoped($workspace, $f, $f->event);
        if (! $q) {
            return [];
        }

        $table = self::TABLES[$f->event];
        // Format AFTER grouping: to_char on ~100 buckets instead of on every event row.
        $inner = $q->selectRaw("date_trunc('{$unit}', {$table}.occurred_at AT TIME ZONE ?) as bucket, ".$this->metrics($f->event), [$f->timezone])->groupBy('bucket');
        $rows = DB::query()->fromSub($inner, 't')->selectRaw("to_char(t.bucket, 'YYYY-MM-DD\"T\"HH24:MI:SS') as label, ".$this->outerColumns($f->event))->get();

        return $rows->mapWithKeys(fn ($r) => [$r->label => $this->metricValues($f->event, $r)])->all();
    }

    private function outerColumns(string $event): string
    {
        return match ($event) {
            'clicks' => 't.n, t.u',
            'leads' => 't.n',
            'sales' => 't.n, t.amount',
        };
    }

    /** @return array<string, array<string, int>> */
    private function rollupSeries(Workspace $workspace, AnalyticsFilters $f, string $unit, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $q = $this->rollupScoped($workspace, $f, $from, $to);
        if (! $q) {
            return [];
        }

        $select = match ($f->event) {
            'clicks' => 'coalesce(sum(clicks), 0) as n, coalesce(sum(unique_clicks), 0) as u',
            'leads' => 'coalesce(sum(leads), 0) as n',
            'sales' => 'coalesce(sum(sales), 0) as n, coalesce(sum(sale_amount), 0)::bigint as amount',
        };

        $rows = $q->selectRaw("to_char(date_trunc('{$unit}', analytics_daily.day::timestamp), 'YYYY-MM-DD\"T\"HH24:MI:SS') as bucket, {$select}")->groupBy('bucket')->get();

        return $rows->mapWithKeys(fn ($r) => [$r->bucket => $this->metricValues($f->event, $r)])->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function top(Workspace $workspace, AnalyticsFilters $f, string $groupBy, int $limit = 100): array
    {
        $q = $this->scoped($workspace, $f, $f->event);
        if (! $q) {
            return [];
        }

        $expression = $groupBy === 'top_links' ? 'link_id' : self::GROUPS[$groupBy];
        $rows = $q->selectRaw("{$expression} as label, ".$this->metrics($f->event))
            ->groupByRaw($expression)->orderByDesc('n')->orderByRaw('label')->limit($limit)->get();

        if ($groupBy === 'top_links') {
            return $this->withLinks($workspace, $rows, $f->event);
        }

        $key = ['top_urls' => 'url', 'refererUrl' => 'refererUrl'][$groupBy] ?? $groupBy;

        return $rows->map(fn ($r) => [$key => is_string($r->label) ? trim($r->label) : $r->label, ...$this->metricValues($f->event, $r)])->all();
    }

    private function metrics(string $event): string
    {
        return match ($event) {
            'clicks' => 'count(*) as n, count(*) filter (where is_unique) as u',
            'leads' => 'count(*) as n',
            'sales' => 'count(*) as n, coalesce(sum(amount), 0)::bigint as amount',
        };
    }

    /** @return array<string, int> */
    private function metricValues(string $event, object $row): array
    {
        return match ($event) {
            'clicks' => ['clicks' => (int) $row->n, 'uniqueClicks' => (int) $row->u],
            'leads' => ['leads' => (int) $row->n],
            'sales' => ['sales' => (int) $row->n, 'saleAmount' => (int) $row->amount],
        };
    }

    /** Join link details for the (≤ limit) ranked ids with one extra query, not one per row. */
    private function withLinks(Workspace $workspace, $rows, string $event): array
    {
        $links = Link::query()->where('workspace_id', $workspace->id)->whereIn('id', $rows->pluck('label')->map(fn ($id) => trim($id)))
            ->get(['id', 'domain', 'key', 'url', 'title'])->keyBy('id');

        return $rows->map(function ($r) use ($links, $event) {
            $link = $links->get(trim($r->label));

            return [
                'link' => $link ? ['id' => $link->id, 'domain' => $link->domain, 'key' => $link->key, 'shortLink' => $link->short_link, 'url' => $link->url, 'title' => $link->title] : ['id' => trim($r->label), 'deleted' => true],
                ...$this->metricValues($event, $r),
            ];
        })->all();
    }

    /** Zero-filled bucket skeleton so charts never have holes. @return array<string, array<string, mixed>> */
    private function emptyBuckets(AnalyticsFilters $f, string $unit): array
    {
        $zero = match ($f->event) {
            'clicks' => ['clicks' => 0, 'uniqueClicks' => 0], 'leads' => ['leads' => 0], 'sales' => ['sales' => 0, 'saleAmount' => 0],
        };

        $step = ['hour' => '1 hour', 'day' => '1 day', 'month' => '1 month'][$unit];
        $cursor = $f->start->setTimezone($f->timezone)->startOf($unit);
        $last = $f->end->setTimezone($f->timezone);
        $buckets = [];

        for ($i = 0; $cursor <= $last && $i < 1000; $i++, $cursor = $cursor->modify("+{$step}")) {
            $key = $cursor->format('Y-m-d\TH:i:s');
            $buckets[$key] = ['start' => $key, ...$zero];
        }

        return $buckets;
    }
}
