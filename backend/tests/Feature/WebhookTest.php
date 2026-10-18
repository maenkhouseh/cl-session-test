<?php

namespace Tests\Feature;

use App\Actions\Links\FlushClicks;
use App\Enums\Plan;
use App\Jobs\DeliverWebhook;
use App\Models\Link;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\ClickBuffer;
use App\Support\SafeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesWorkspaces;
use Tests\Concerns\SeedsEvents;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase, SeedsEvents;

    private $user;

    private $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->user, $this->workspace] = $this->ownerWithWorkspace();
    }

    private function api()
    {
        return $this->actingInWorkspace($this->user, $this->workspace);
    }

    private function hook(array $attrs = []): Webhook
    {
        return Webhook::factory()->create(['workspace_id' => $this->workspace->id, ...$attrs]);
    }

    // ---- management --------------------------------------------------------------------------

    public function test_creates_a_webhook_and_reveals_the_secret_once(): void
    {
        $created = $this->api()->postJson('/api/v1/webhooks', ['name' => 'CRM', 'url' => 'https://hooks.example.com/in', 'triggers' => ['link.created', 'sale.created']])
            ->assertCreated()->assertJsonPath('data.name', 'CRM')->assertJsonPath('data.disabled', false);

        $this->assertStringStartsWith('whsec_', $created->json('data.secret'));
        $stored = Webhook::first();
        $this->assertSame($created->json('data.secret'), $stored->secret);
        $this->assertNotSame($created->json('data.secret'), $stored->getRawOriginal('secret')); // encrypted at rest

        $this->api()->getJson('/api/v1/webhooks')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.secret');
    }

    public function test_validates_url_and_triggers(): void
    {
        $bad = [
            ['url' => 'not-a-url'], ['url' => 'ftp://x.com'], ['triggers' => []], ['triggers' => ['link.exploded']],
            ['triggers' => ['link.created', 'link.created']], ['name' => ''],
        ];

        foreach ($bad as $override) {
            $this->api()->postJson('/api/v1/webhooks', ['name' => 'W', 'url' => 'https://hooks.example.com', 'triggers' => ['link.created'], ...$override])->assertStatus(422);
        }
    }

    public function test_webhooks_are_a_paid_feature_and_capped_per_workspace(): void
    {
        [$free, $freeWs] = $this->ownerWithWorkspace(Plan::Free);
        $this->actingInWorkspace($free, $freeWs)->postJson('/api/v1/webhooks', ['name' => 'W', 'url' => 'https://h.example.com', 'triggers' => ['link.created']])->assertForbidden();

        Webhook::factory()->count(10)->create(['workspace_id' => $this->workspace->id]);
        $this->api()->postJson('/api/v1/webhooks', ['name' => 'W', 'url' => 'https://h.example.com', 'triggers' => ['link.created']])
            ->assertForbidden()->assertJsonPath('error.code', 'exceeded_limit');
    }

    public function test_update_and_delete_are_workspace_scoped(): void
    {
        $mine = $this->hook();
        $foreign = Webhook::factory()->create();

        $this->api()->patchJson("/api/v1/webhooks/{$mine->id}", ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');
        $this->api()->patchJson("/api/v1/webhooks/{$foreign->id}", ['name' => 'x'])->assertNotFound();
        $this->api()->deleteJson("/api/v1/webhooks/{$foreign->id}")->assertNotFound();
        $this->api()->deleteJson("/api/v1/webhooks/{$mine->id}")->assertOk();
        $this->assertModelMissing($mine);
    }

    public function test_editing_an_auto_disabled_webhook_re_enables_it(): void
    {
        $hook = $this->hook(['disabled_at' => now(), 'consecutive_failures' => 20]);

        $this->api()->patchJson("/api/v1/webhooks/{$hook->id}", ['name' => 'Fixed'])->assertJsonPath('data.disabled', false)->assertJsonPath('data.consecutiveFailures', 0);
    }

    // ---- delivery ----------------------------------------------------------------------------

    public function test_link_created_is_delivered_signed(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response(['ok' => true], 200)]);
        $hook = $this->hook(['url' => 'https://hooks.example.com/in', 'triggers' => ['link.created']]);

        $this->api()->postJson('/api/v1/links', ['url' => 'https://dest.com', 'key' => 'hooked'])->assertCreated();

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($hook) {
            $body = $request->body();
            preg_match('/t=(\d+),v1=([a-f0-9]{64})/', $request->header('Shortie-Signature')[0], $m);

            return $request->url() === 'https://hooks.example.com/in'
                && $request->header('Shortie-Event')[0] === 'link.created'
                && hash_equals(hash_hmac('sha256', $m[1].'.'.$body, $hook->secret), $m[2])
                && json_decode($body, true)['data']['key'] === 'hooked'
                && str_starts_with(json_decode($body, true)['id'], 'evt_');
        });

        $delivery = WebhookDelivery::first();
        $this->assertSame(['success', 200, 1], [$delivery->status, $delivery->response_code, $delivery->attempts]);
    }

    public function test_only_subscribed_events_on_active_webhooks_are_delivered(): void
    {
        Http::fake();
        $this->hook(['triggers' => ['sale.created']]);
        $this->hook(['triggers' => ['link.created'], 'disabled_at' => now()]);
        $foreign = Webhook::factory()->create(['triggers' => ['link.created']]);

        $this->api()->postJson('/api/v1/links', ['url' => 'https://dest.com'])->assertCreated();

        Http::assertNothingSent();
        $this->assertSame(0, $foreign->deliveries()->count());
    }

    public function test_update_and_delete_events_are_delivered(): void
    {
        Http::fake();
        $this->hook(['triggers' => ['link.updated', 'link.deleted']]);
        $link = Link::factory()->create(['workspace_id' => $this->workspace->id]);

        $this->api()->patchJson("/api/v1/links/{$link->id}", ['title' => 'T'])->assertOk();
        $this->api()->deleteJson("/api/v1/links/{$link->id}")->assertOk();

        $events = WebhookDelivery::orderBy('id')->pluck('event')->all();
        $this->assertSame(['link.updated', 'link.deleted'], $events);
    }

    public function test_lead_and_sale_events_are_delivered(): void
    {
        Http::fake();
        $this->hook(['triggers' => ['lead.created', 'sale.created']]);
        $link = Link::factory()->create(['workspace_id' => $this->workspace->id]);
        $this->seedClick($link, ['click_id' => 'cid123']);

        $this->api()->postJson('/api/v1/track/lead', ['clickId' => 'cid123', 'eventName' => 'Sign up', 'customerExternalId' => 'u1', 'customerEmail' => 'u@example.com'])->assertCreated();
        $this->api()->postJson('/api/v1/track/lead', ['clickId' => 'cid123', 'eventName' => 'Sign up', 'customerExternalId' => 'u1'])->assertOk(); // replay: no second event
        $this->api()->postJson('/api/v1/track/sale', ['customerExternalId' => 'u1', 'amount' => 4900, 'invoiceId' => 'i1'])->assertCreated();

        $deliveries = WebhookDelivery::orderBy('id')->get();
        $this->assertSame(['lead.created', 'sale.created'], $deliveries->pluck('event')->all());
        $this->assertSame('u@example.com', $deliveries[0]->payload['data']['customer']['email']);
        $this->assertSame(4900, $deliveries[1]->payload['data']['sale']['amount']);
    }

    public function test_click_events_are_delivered_only_to_subscribers(): void
    {
        Http::fake();
        app(ClickBuffer::class)->clear();
        $link = Link::factory()->create(['workspace_id' => $this->workspace->id]);
        $this->hook(['triggers' => ['link.clicked']]);
        $other = Link::factory()->create(); // another workspace, nobody subscribed there

        app(ClickBuffer::class)->push(['occurred_at' => now()->utc()->format('Y-m-d H:i:s.u'), 'workspace_id' => $link->workspace_id, 'link_id' => $link->id, 'click_id' => 'c1', 'is_unique' => true, 'url' => 'https://a.com', 'country' => 'DE']);
        app(ClickBuffer::class)->push(['occurred_at' => now()->utc()->format('Y-m-d H:i:s.u'), 'workspace_id' => $other->workspace_id, 'link_id' => $other->id, 'click_id' => 'c2', 'is_unique' => true, 'url' => 'https://a.com']);
        app(FlushClicks::class)();

        $this->assertSame(1, WebhookDelivery::count());
        $this->assertSame('DE', WebhookDelivery::first()->payload['data']['click']['country']);
        app(ClickBuffer::class)->clear();
    }

    public function test_no_work_is_done_when_nobody_is_subscribed(): void
    {
        Queue::fake();

        $this->api()->postJson('/api/v1/links', ['url' => 'https://dest.com'])->assertCreated();

        Queue::assertNothingPushed();
    }

    public function test_the_subscriber_lookup_is_cached_and_invalidated_on_change(): void
    {
        Http::fake();
        $this->api()->postJson('/api/v1/links', ['url' => 'https://a.com'])->assertCreated(); // caches "no webhooks"
        $this->assertTrue(Cache::has("webhooks:{$this->workspace->id}"));

        $this->api()->postJson('/api/v1/webhooks', ['name' => 'W', 'url' => 'https://hooks.example.com', 'triggers' => ['link.created']])->assertCreated();
        $this->api()->postJson('/api/v1/links', ['url' => 'https://b.com'])->assertCreated();

        $this->assertSame(1, WebhookDelivery::count());
    }

    // ---- failures ----------------------------------------------------------------------------

    public function test_a_failing_endpoint_is_recorded_and_counted(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);
        $hook = $this->hook(['triggers' => ['link.created']]);

        $this->api()->postJson('/api/v1/links', ['url' => 'https://dest.com'])->assertCreated(); // the user's request is never affected

        $delivery = WebhookDelivery::first();
        $this->assertSame(['pending', 500, 1], [$delivery->status, $delivery->response_code, $delivery->attempts]); // pending = will retry
        $this->assertSame('boom', $delivery->response_body);
        $hook->refresh();
        $this->assertSame(1, $hook->consecutive_failures);
        $this->assertNotNull($hook->last_failed_at);
    }

    public function test_connection_errors_are_failures_not_crashes(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $hook = $this->hook(['triggers' => ['link.created']]);

        $this->api()->postJson('/api/v1/links', ['url' => 'https://dest.com'])->assertCreated();

        $this->assertSame(1, $hook->refresh()->consecutive_failures);
        $this->assertStringContainsString('timeout', WebhookDelivery::first()->response_body);
    }

    public function test_a_webhook_is_disabled_after_too_many_consecutive_failures(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $hook = $this->hook(['triggers' => ['link.created'], 'consecutive_failures' => 19]);

        $this->api()->postJson('/api/v1/links', ['url' => 'https://dest.com'])->assertCreated();

        $this->assertNotNull($hook->refresh()->disabled_at);
        $this->api()->postJson('/api/v1/links', ['url' => 'https://dest2.com'])->assertCreated();
        $this->assertSame(1, WebhookDelivery::count()); // no further attempts once disabled
    }

    public function test_a_success_resets_the_failure_counter(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $hook = $this->hook(['triggers' => ['link.created'], 'consecutive_failures' => 7]);

        $this->api()->postJson('/api/v1/links', ['url' => 'https://dest.com'])->assertCreated();

        $this->assertSame(0, $hook->refresh()->consecutive_failures);
    }

    public function test_redirects_from_the_endpoint_are_not_followed(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data'])]);
        $this->hook(['url' => 'https://hooks.example.com/in', 'triggers' => ['link.created']]);

        $this->api()->postJson('/api/v1/links', ['url' => 'https://dest.com'])->assertCreated();

        Http::assertSentCount(1);
        $this->assertSame('pending', WebhookDelivery::first()->status);
    }

    // ---- SSRF --------------------------------------------------------------------------------

    public function test_private_and_loopback_targets_are_rejected(): void
    {
        foreach (['http://127.0.0.1/hook', 'http://169.254.169.254/latest', 'http://10.0.0.5/x', 'http://192.168.1.1', 'http://[::1]/x', 'https://user:pass@8.8.8.8/x'] as $url) {
            $this->assertFalse(SafeUrl::isSafeWebhookTarget($url), $url);
        }
        $this->assertTrue(SafeUrl::isSafeWebhookTarget('https://8.8.8.8/hook'));
    }

    public function test_creating_a_webhook_to_a_private_address_is_rejected(): void
    {
        $this->api()->postJson('/api/v1/webhooks', ['name' => 'W', 'url' => 'https://10.1.2.3/hook', 'triggers' => ['link.created']])
            ->assertStatus(422)->assertJsonPath('error.code', 'unprocessable_entity');
    }

    public function test_delivery_re_checks_the_target_at_send_time(): void
    {
        Http::fake();
        $hook = $this->hook(['url' => 'https://10.1.2.3/hook', 'triggers' => ['link.created']]); // e.g. DNS changed after creation

        $this->api()->postJson('/api/v1/links', ['url' => 'https://dest.com'])->assertCreated();

        Http::assertNothingSent();
        $this->assertSame(1, $hook->refresh()->consecutive_failures);
    }

    // ---- tooling -----------------------------------------------------------------------------

    public function test_deliveries_listing_and_test_endpoint(): void
    {
        Http::fake();
        $hook = $this->hook();

        $this->api()->postJson("/api/v1/webhooks/{$hook->id}/test")->assertStatus(202)->assertJsonPath('queued', true);
        $this->api()->getJson("/api/v1/webhooks/{$hook->id}/deliveries")->assertOk()->assertJsonPath('data.0.event', 'webhook.test')->assertJsonPath('data.0.status', 'success');
    }

    public function test_deliveries_of_other_workspaces_webhooks_are_not_visible(): void
    {
        $foreign = Webhook::factory()->create();

        $this->api()->getJson("/api/v1/webhooks/{$foreign->id}/deliveries")->assertNotFound();
        $this->api()->postJson("/api/v1/webhooks/{$foreign->id}/test")->assertNotFound();
    }

    public function test_job_is_a_no_op_for_a_deleted_webhook(): void
    {
        Http::fake();

        (new DeliverWebhook('01doesnotexist00000000000000', 'link.created', ['id' => 'evt_1']))->handle();

        Http::assertNothingSent();
        $this->assertSame(0, WebhookDelivery::count());
    }
}
