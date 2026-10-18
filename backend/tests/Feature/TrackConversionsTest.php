<?php

namespace Tests\Feature;

use App\Actions\Links\FlushClicks;
use App\Enums\Plan;
use App\Models\Customer;
use App\Models\Link;
use App\Services\ClickBuffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesWorkspaces;
use Tests\Concerns\SeedsEvents;
use Tests\TestCase;

class TrackConversionsTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase, SeedsEvents;

    private function setUpClick(Plan $plan = Plan::Business, array $click = []): array
    {
        [$user, $workspace] = $this->ownerWithWorkspace($plan);
        $link = Link::factory()->create(['workspace_id' => $workspace->id]);
        $this->seedClick($link, ['click_id' => 'clickabc123', 'country' => 'DE', 'utm_source' => 'nl', 'qr' => true, ...$click]);

        return [$user, $workspace, $link];
    }

    private function lead(array $o = [])
    {
        return ['clickId' => 'clickabc123', 'eventName' => 'Sign up', 'customerExternalId' => 'user_1', 'customerName' => 'Ada', 'customerEmail' => 'ADA@example.com', ...$o];
    }

    public function test_tracks_a_lead_and_creates_the_customer_with_first_touch_attribution(): void
    {
        [$user, $workspace, $link] = $this->setUpClick();

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/track/lead', $this->lead())
            ->assertCreated()
            ->assertJsonPath('click.id', 'clickabc123')
            ->assertJsonPath('link.id', $link->id)
            ->assertJsonPath('customer.externalId', 'user_1')
            ->assertJsonPath('customer.email', 'ada@example.com');

        $customer = Customer::firstWhere('external_id', 'user_1');
        $this->assertSame($link->id, $customer->link_id);
        $this->assertSame('clickabc123', $customer->click_id);
        $this->assertSame('DE', $customer->country);

        $event = DB::table('lead_events')->first();
        $this->assertSame('Sign up', $event->event_name);
        $this->assertSame('DE', trim($event->country));      // dimensions are copied from the click…
        $this->assertSame('nl', $event->utm_source);
        $this->assertTrue((bool) $event->qr);                // …so conversions filter exactly like clicks
        $this->assertSame(1, $link->refresh()->leads);
        $this->assertSame(1, $workspace->refresh()->events_usage);
    }

    public function test_lead_tracking_is_idempotent_per_customer_and_event_name(): void
    {
        [$user, $workspace, $link] = $this->setUpClick();
        $api = fn () => $this->actingInWorkspace($user, $workspace);

        $api()->postJson('/api/v1/track/lead', $this->lead())->assertCreated();
        $api()->postJson('/api/v1/track/lead', $this->lead())->assertOk();
        $api()->postJson('/api/v1/track/lead', $this->lead(['eventName' => 'Trial started']))->assertCreated();

        $this->assertSame(2, DB::table('lead_events')->count());
        $this->assertSame(2, $link->refresh()->leads);
        $this->assertSame(2, $workspace->refresh()->events_usage);
        $this->assertSame(1, Customer::count());
    }

    public function test_a_returning_customer_updates_their_profile_but_keeps_first_touch(): void
    {
        [$user, $workspace, $link] = $this->setUpClick();
        $other = Link::factory()->create(['workspace_id' => $workspace->id]);
        $this->seedClick($other, ['click_id' => 'secondclick']);
        $api = fn () => $this->actingInWorkspace($user, $workspace);

        $api()->postJson('/api/v1/track/lead', $this->lead(['customerName' => 'Ada']))->assertCreated();
        $api()->postJson('/api/v1/track/lead', $this->lead(['clickId' => 'secondclick', 'eventName' => 'Upgrade', 'customerName' => 'Ada L.']))->assertCreated();

        $customer = Customer::first();
        $this->assertSame('Ada L.', $customer->name);
        $this->assertSame($link->id, $customer->link_id);
        $this->assertSame('clickabc123', $customer->click_id);
    }

    public function test_unknown_expired_or_foreign_clicks_are_rejected(): void
    {
        [$user, $workspace] = $this->setUpClick();
        [, , $foreignLink] = $this->setUpClick();
        $this->seedClick($foreignLink, ['click_id' => 'foreignclick']);
        $this->seedClick(Link::factory()->create(['workspace_id' => $workspace->id]), ['click_id' => 'oldclick', 'occurred_at' => now()->subDays(120)]);

        foreach (['doesnotexist', 'foreignclick', 'oldclick'] as $id) {
            $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/track/lead', $this->lead(['clickId' => $id]))
                ->assertNotFound()->assertJsonPath('error.code', 'not_found');
        }
        $this->assertSame(0, DB::table('lead_events')->count());
    }

    public function test_conversion_tracking_is_a_paid_feature(): void
    {
        [$user, $workspace] = $this->setUpClick(Plan::Free);

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/track/lead', $this->lead())->assertForbidden();
        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/track/sale', ['customerExternalId' => 'x', 'amount' => 1])->assertForbidden();
    }

    public function test_lead_validation(): void
    {
        [$user, $workspace] = $this->setUpClick();

        foreach ([['clickId' => ''], ['eventName' => ''], ['customerExternalId' => ''], ['customerEmail' => 'nope'], ['customerAvatar' => 'x'], ['timestamp' => now()->addDay()->toIso8601String()]] as $bad) {
            $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/track/lead', $this->lead($bad))->assertStatus(422);
        }
    }

    public function test_tracks_a_sale_and_updates_link_revenue(): void
    {
        [$user, $workspace, $link] = $this->setUpClick();
        $api = fn () => $this->actingInWorkspace($user, $workspace);
        $api()->postJson('/api/v1/track/lead', $this->lead())->assertCreated();

        $api()->postJson('/api/v1/track/sale', ['customerExternalId' => 'user_1', 'amount' => 4900, 'currency' => 'EUR', 'invoiceId' => 'inv_1'])
            ->assertCreated()->assertJsonPath('sale.amount', 4900)->assertJsonPath('sale.currency', 'eur')->assertJsonPath('customer.externalId', 'user_1');
        $api()->postJson('/api/v1/track/sale', ['customerExternalId' => 'user_1', 'amount' => 1000])->assertCreated();

        $link->refresh();
        $this->assertSame(2, $link->sales);
        $this->assertSame(5900, $link->sale_amount);
        $sale = DB::table('sale_events')->orderBy('id')->first();
        $this->assertSame('DE', trim($sale->country));
        $this->assertSame('Purchase', $sale->event_name);
        $this->assertSame('eur', trim($sale->currency));
    }

    public function test_sales_are_idempotent_per_invoice(): void
    {
        [$user, $workspace, $link] = $this->setUpClick();
        $api = fn () => $this->actingInWorkspace($user, $workspace);
        $api()->postJson('/api/v1/track/lead', $this->lead())->assertCreated();
        $sale = ['customerExternalId' => 'user_1', 'amount' => 4900, 'invoiceId' => 'inv_1'];

        $api()->postJson('/api/v1/track/sale', $sale)->assertCreated();
        $api()->postJson('/api/v1/track/sale', $sale)->assertOk();

        $this->assertSame(1, DB::table('sale_events')->count());
        $this->assertSame(4900, $link->refresh()->sale_amount);
        $this->assertSame(2, $workspace->refresh()->events_usage); // 1 lead + 1 sale
    }

    public function test_sales_need_a_known_attributed_customer(): void
    {
        [$user, $workspace] = $this->setUpClick();
        Customer::factory()->create(['workspace_id' => $workspace->id, 'external_id' => 'organic']);

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/track/sale', ['customerExternalId' => 'ghost', 'amount' => 100])->assertNotFound();
        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/track/sale', ['customerExternalId' => 'organic', 'amount' => 100])->assertStatus(422);
    }

    public function test_sale_validation(): void
    {
        [$user, $workspace] = $this->setUpClick();

        foreach ([['amount' => -1], ['amount' => 'ten'], ['amount' => 1.5], ['currency' => 'dollars'], ['paymentProcessor' => 'bitcoin']] as $bad) {
            $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/track/sale', ['customerExternalId' => 'user_1', 'amount' => 100, ...$bad])->assertStatus(422);
        }
    }

    public function test_conversions_stop_when_the_events_quota_is_exhausted(): void
    {
        [$user, $workspace, $link] = $this->setUpClick();
        $workspace->update(['plan' => Plan::Business, 'events_usage' => 250_000]);

        $this->actingInWorkspace($user, $workspace)->postJson('/api/v1/track/lead', $this->lead())
            ->assertForbidden()->assertJsonPath('error.code', 'exceeded_limit');

        $this->assertSame(0, DB::table('lead_events')->count());
        $this->assertSame(0, Customer::count()); // the whole operation rolled back
        $this->assertSame(250_000, $workspace->refresh()->events_usage);
    }

    public function test_full_funnel_click_to_lead_to_sale_to_analytics(): void
    {
        app(ClickBuffer::class)->clear();
        [$user, $workspace] = $this->ownerWithWorkspace();
        $link = Link::factory()->create(['workspace_id' => $workspace->id, 'key' => 'promo', 'url' => 'https://app.example.com/signup', 'track_conversion' => true]);

        // 1. Visitor clicks the short link and lands with the click id attached.
        $location = $this->flushHeaders()->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/124.0 Safari/537.36', 'CF-IPCountry' => 'CA'])
            ->get('http://shortie.link/promo')->assertRedirect()->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        app(FlushClicks::class)();

        // 2. They sign up, then pay — the app reports both server-side.
        $api = fn () => $this->actingInWorkspace($user, $workspace);
        $api()->postJson('/api/v1/track/lead', ['clickId' => $query['shortie_id'], 'eventName' => 'Sign up', 'customerExternalId' => 'u1', 'customerEmail' => 'u1@example.com'])->assertCreated();
        $api()->postJson('/api/v1/track/sale', ['customerExternalId' => 'u1', 'amount' => 2900, 'invoiceId' => 'in_1'])->assertCreated();

        // 3. Everything is attributable and filterable by the click's country.
        $api()->getJson('/api/v1/analytics?groupBy=count&interval=7d&country=CA')
            ->assertOk()->assertJsonPath('clicks', 1)->assertJsonPath('leads', 1)->assertJsonPath('sales', 1)->assertJsonPath('saleAmount', 2900);
        $api()->getJson('/api/v1/analytics?groupBy=count&interval=7d&country=US')
            ->assertJsonPath('clicks', 0)->assertJsonPath('leads', 0)->assertJsonPath('sales', 0);

        $link->refresh();
        $this->assertSame([1, 1, 1, 2900], [$link->clicks, $link->leads, $link->sales, $link->sale_amount]);
        app(ClickBuffer::class)->clear();
    }
}
