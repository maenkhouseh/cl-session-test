<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\SafeUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    /** Seconds before retry n (attempt 1 failed → wait 10s, …). */
    private const BACKOFF = [10, 60, 300, 1800, 7200];

    public function __construct(public string $webhookId, public string $event, public array $payload, public ?string $deliveryId = null) {}

    public function handle(): void
    {
        $webhook = Webhook::query()->find($this->webhookId);

        if (! $webhook || ! $webhook->isActive()) {
            return;
        }

        $delivery = $this->delivery();
        $delivery->increment('attempts');

        try {
            $body = json_encode($this->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (! SafeUrl::isSafeWebhookTarget($webhook->url)) {
                throw new \RuntimeException('Webhook target is not a public https URL.');
            }

            $response = Http::timeout(10)->withoutRedirecting()->withHeaders($this->headers($webhook, $body))
                ->withBody($body, 'application/json')->post($webhook->url);

            $delivery->update(['response_code' => $response->status(), 'response_body' => mb_substr($response->body(), 0, 2000)]);

            if ($response->successful()) {
                $delivery->update(['status' => 'success']);
                $webhook->update(['consecutive_failures' => 0]);

                return;
            }
        } catch (Throwable $e) {
            $delivery->update(['response_body' => mb_substr($e->getMessage(), 0, 2000)]);
        }

        $this->recordFailure($webhook, $delivery);
    }

    /** @return array<string, string> */
    private function headers(Webhook $webhook, string $body): array
    {
        $timestamp = time();

        return [
            'User-Agent' => config('app.name').'-Webhooks/1.0',
            'Shortie-Event' => $this->event,
            'Shortie-Delivery' => (string) ($this->payload['id'] ?? ''),
            // Verify with: hmac_sha256("{t}.{body}", secret) === v1
            'Shortie-Signature' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $webhook->secret),
        ];
    }

    private function recordFailure(Webhook $webhook, WebhookDelivery $delivery): void
    {
        $failures = $webhook->consecutive_failures + 1;
        $final = $this->attempts() >= $this->tries;

        $webhook->update([
            'consecutive_failures' => $failures,
            'last_failed_at' => now(),
            'disabled_at' => $failures >= Webhook::DISABLE_AFTER_FAILURES ? now() : $webhook->disabled_at,
        ]);

        $delivery->update(['status' => $final ? 'failed' : 'pending']);

        if (! $final) {
            $this->deliveryId = $delivery->id;
            $this->release(self::BACKOFF[min($this->attempts() - 1, count(self::BACKOFF) - 1)]);
        }
    }

    private function delivery(): WebhookDelivery
    {
        return $this->deliveryId
            ? WebhookDelivery::query()->findOrFail($this->deliveryId)
            : WebhookDelivery::create(['webhook_id' => $this->webhookId, 'event' => $this->event, 'payload' => $this->payload, 'status' => 'pending']);
    }
}
