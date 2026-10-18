<?php

namespace App\Actions\Links;

use App\Models\Workspace;
use App\Services\ClickBuffer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Drains buffered click events into click_events and keeps the denormalised counters current. */
class FlushClicks
{
    private const COLUMNS = [
        'occurred_at', 'workspace_id', 'link_id', 'click_id', 'is_unique', 'url', 'country', 'region', 'city', 'continent',
        'device', 'browser', 'os', 'referer', 'referer_url', 'qr', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    ];

    public function __construct(private ClickBuffer $buffer) {}

    /** @return int number of events persisted */
    public function __invoke(int $batch = 1000): int
    {
        $events = collect($this->buffer->drain($batch));

        if ($events->isEmpty()) {
            return 0;
        }

        $admitted = $this->withinQuota($events);

        if ($admitted->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($admitted) {
            foreach ($admitted->chunk(500) as $chunk) {
                DB::table('click_events')->insert($chunk->map(fn ($e) => $this->row($e))->all());
            }

            $this->bumpLinkCounters($admitted);
            $this->bumpWorkspaceUsage($admitted);
        });

        return $admitted->count();
    }

    /**
     * Events beyond a workspace's monthly tracked-events quota are dropped (links keep redirecting).
     *
     * @param  Collection<int, array<string, mixed>>  $events
     * @return Collection<int, array<string, mixed>>
     */
    private function withinQuota(Collection $events): Collection
    {
        $workspaces = Workspace::query()->whereIn('id', $events->pluck('workspace_id')->unique())->get(['id', 'plan', 'events_usage'])->keyBy('id');

        return $events->groupBy('workspace_id')->flatMap(function (Collection $group, string $workspaceId) use ($workspaces) {
            $workspace = $workspaces->get($workspaceId);

            if (! $workspace) {
                return []; // workspace deleted while events were in flight
            }

            $limit = $workspace->plan->limit('events_per_month');

            return $limit === null ? $group : $group->take(max($limit - $workspace->events_usage, 0));
        })->values();
    }

    private function row(array $event): array
    {
        $row = [];
        foreach (self::COLUMNS as $column) {
            $row[$column] = $event[$column] ?? null;
        }
        $row['is_unique'] = (bool) $row['is_unique'];
        $row['qr'] = (bool) $row['qr'];
        $row['occurred_at'] .= '+00';

        return $row;
    }

    private function bumpLinkCounters(Collection $events): void
    {
        $perLink = $events->groupBy('link_id')->map(fn ($g) => ['count' => $g->count(), 'last' => $g->max('occurred_at')]);

        $values = $perLink->map(fn () => '(?, ?::bigint, ?::timestamptz)')->implode(', ');
        $bindings = $perLink->flatMap(fn ($v, $id) => [$id, $v['count'], $v['last'].'+00'])->values()->all();

        DB::update("UPDATE links SET clicks = links.clicks + v.c, last_clicked_at = GREATEST(links.last_clicked_at, v.t)
                    FROM (VALUES {$values}) AS v(id, c, t) WHERE links.id = v.id", $bindings);
    }

    private function bumpWorkspaceUsage(Collection $events): void
    {
        $perWorkspace = $events->countBy('workspace_id');

        $values = $perWorkspace->map(fn () => '(?, ?::bigint)')->implode(', ');
        $bindings = $perWorkspace->flatMap(fn ($count, $id) => [$id, $count])->values()->all();

        DB::update("UPDATE workspaces SET events_usage = workspaces.events_usage + v.c
                    FROM (VALUES {$values}) AS v(id, c) WHERE workspaces.id = v.id", $bindings);
    }
}
