<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Models\Workspace;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Fans an event out to the workspace's subscribed webhooks as queued, individually retried deliveries. */
class WebhookDispatcher
{
    /**
     * @param  Closure(): array<string, mixed>  $data  built lazily — only when somebody is listening
     */
    public function dispatch(Workspace|string $workspace, string $event, Closure $data): void
    {
        $webhooks = $this->subscribed($workspace instanceof Workspace ? $workspace->id : $workspace, $event);

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = ['id' => 'evt_'.Str::lower((string) Str::ulid()), 'event' => $event, 'createdAt' => now()->toIso8601String(), 'data' => $data()];

        foreach ($webhooks as $webhook) {
            DeliverWebhook::dispatch($webhook->id, $event, $payload);
        }
    }

    public function hasSubscribers(string $workspaceId, string $event): bool
    {
        return $this->subscribed($workspaceId, $event)->isNotEmpty();
    }

    public function forget(string $workspaceId): void
    {
        Cache::forget($this->cacheKey($workspaceId));
    }

    /** One cached query per workspace per minute — the hot paths must not pay for webhooks they don't use. */
    private function subscribed(string $workspaceId, string $event)
    {
        return Cache::remember($this->cacheKey($workspaceId), 60, fn () => Webhook::query()
            ->where('workspace_id', $workspaceId)->whereNull('disabled_at')->get(['id', 'triggers']))
            ->filter(fn ($w) => in_array($event, $w->triggers, true));
    }

    private function cacheKey(string $workspaceId): string
    {
        return "webhooks:{$workspaceId}";
    }
}
