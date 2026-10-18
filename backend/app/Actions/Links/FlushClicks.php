<?php

namespace App\Actions\Links;

use App\Models\Workspace;
use App\Services\Analytics\DailyRollup;
use App\Services\ClickBuffer;
use App\Services\WebhookDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Drains buffered click events into click_events and keeps the denormalised counters current. */
class FlushClicks
{
    private const COLUMNS = [
        'occurred_at', 'workspace_id', 'link_id', 'click_id', 'is_unique', 'url', 'country', 'region', 'city', 'continent',
        'device', 'browser', 'os', 'referer', 'referer_url', 'qr', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    ];

    public function __construct(private ClickBuffer $buffer, private WebhookDispatcher $webhooks, private DailyRollup $rollup) {}

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
            $this->rollup->increment($this->dailyDeltas($admitted));
            $this->bumpWorkspaceUsage($admitted);
        });

        $this->notifyClickSubscribers($admitted);

        return $admitted->count();
    }

    /** `link.clicked` webhooks — only workspaces that actually subscribe pay for the fan-out. */
    private function notifyClickSubscribers(Collection $events): void
    {
        foreach ($events->groupBy('workspace_id') as $workspaceId => $group) {
            if (! $this->webhooks->hasSubscribers($workspaceId, 'link.clicked')) {
                continue;
            }

            foreach ($group as $event) {
                $this->webhooks->dispatch($workspaceId, 'link.clicked', fn () => [
                    'click' => ['id' => $event['click_id'], 'timestamp' => $event['occurred_at'], 'url' => $event['url'], 'country' => $event['country'] ?? null, 'city' => $event['city'] ?? null, 'device' => $event['device'] ?? null, 'browser' => $event['browser'] ?? null, 'os' => $event['os'] ?? null, 'referer' => $event['referer'] ?? null, 'qr' => (bool) ($event['qr'] ?? false)],
                    'link' => ['id' => $event['link_id']],
                ]);
            }
        }
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

    /** One delta row per (link, UTC day) for the roll-up. */
    private function dailyDeltas(Collection $events): array
    {
        return $events->groupBy(fn ($e) => $e['link_id'].'|'.substr($e['occurred_at'], 0, 10))->map(fn ($group) => [
            'workspace_id' => $group[0]['workspace_id'],
            'link_id' => $group[0]['link_id'],
            'day' => substr($group[0]['occurred_at'], 0, 10),
            'clicks' => $group->count(),
            'unique_clicks' => $group->where('is_unique', true)->count(),
        ])->values()->all();
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
