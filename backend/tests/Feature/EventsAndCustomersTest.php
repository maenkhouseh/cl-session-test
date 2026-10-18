<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Link;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesWorkspaces;
use Tests\Concerns\SeedsEvents;
use Tests\TestCase;

class EventsAndCustomersTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase, SeedsEvents;

    private $user;

    private $workspace;

    private Link $link;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-15 12:30:00');
        [$this->user, $this->workspace] = $this->ownerWithWorkspace();
        $this->link = Link::factory()->create(['workspace_id' => $this->workspace->id, 'domain' => 'shortie.link', 'key' => 'main']);
    }

    private function api()
    {
        return $this->actingInWorkspace($this->user, $this->workspace);
    }

    // ---- events ------------------------------------------------------------------------------

    public function test_lists_events_newest_first_with_link_details(): void
    {
        $this->seedClick($this->link, ['occurred_at' => now()->subMinutes(5), 'country' => 'DE', 'city' => 'Berlin', 'referer' => 'news.com']);
        $this->seedClick($this->link, ['occurred_at' => now()->subMinutes(1), 'country' => 'US']);

        $response = $this->api()->getJson('/api/v1/events?event=clicks&interval=24h')->assertOk();

        $response->assertJsonCount(2, 'data')->assertJsonPath('data.0.country', 'US')->assertJsonPath('data.1.city', 'Berlin')
            ->assertJsonPath('data.0.link.shortLink', 'https://shortie.link/main')->assertJsonPath('data.0.event', 'click')
            ->assertJsonPath('meta.nextCursor', null);
        $this->assertSame('2026-06-15T12:29:00+00:00', $response->json('data.0.timestamp'));
    }

    public function test_keyset_pagination_walks_every_event_exactly_once(): void
    {
        foreach (range(1, 7) as $i) {
            $this->seedClick($this->link, ['occurred_at' => now()->subMinutes($i), 'city' => "c{$i}"]);
        }
        // Same-timestamp rows must not be skipped or repeated.
        $this->seedClick($this->link, ['occurred_at' => now()->subMinutes(3), 'city' => 'tie'], 1);

        $seen = [];
        $cursor = null;
        $pages = 0;
        do {
            $qs = 'event=clicks&interval=24h&limit=3'.($cursor ? "&cursor={$cursor}" : '');
            $page = $this->api()->getJson("/api/v1/events?{$qs}")->assertOk()->json();
            array_push($seen, ...array_column($page['data'], 'city'));
            $cursor = $page['meta']['nextCursor'];
            $pages++;
        } while ($cursor && $pages < 10);

        $this->assertSame(3, $pages);
        $this->assertCount(8, $seen);
        $this->assertCount(8, array_unique($seen));
    }

    public function test_a_tampered_cursor_is_ignored_rather_than_crashing(): void
    {
        $this->seedClick($this->link);

        foreach (['garbage', base64_encode('{"x":1}'), base64_encode('[1,2,3]')] as $cursor) {
            $this->api()->getJson('/api/v1/events?cursor='.urlencode($cursor))->assertOk()->assertJsonCount(1, 'data');
        }
    }

    public function test_lead_and_sale_events_include_the_customer(): void
    {
        $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Ada', 'email' => 'ada@example.com']);
        $this->seedLead($this->link, $customer);
        $this->seedSale($this->link, $customer, 4900, ['invoice_id' => 'inv_9']);

        $this->api()->getJson('/api/v1/events?event=leads')->assertJsonPath('data.0.customer.email', 'ada@example.com')->assertJsonPath('data.0.eventName', 'Sign up');
        $this->api()->getJson('/api/v1/events?event=sales')->assertJsonPath('data.0.amount', 4900)->assertJsonPath('data.0.invoiceId', 'inv_9')->assertJsonPath('data.0.currency', 'usd');
    }

    public function test_events_honour_the_same_filters_as_analytics(): void
    {
        $this->seedClick($this->link, ['country' => 'DE']);
        $this->seedClick($this->link, ['country' => 'US']);

        $this->api()->getJson('/api/v1/events?country=DE')->assertJsonCount(1, 'data')->assertJsonPath('data.0.country', 'DE');
        $this->api()->getJson('/api/v1/events?country=-DE')->assertJsonCount(1, 'data')->assertJsonPath('data.0.country', 'US');
    }

    public function test_listing_events_runs_a_constant_number_of_queries(): void
    {
        $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
        foreach (range(1, 25) as $i) {
            $link = Link::factory()->create(['workspace_id' => $this->workspace->id]);
            $this->seedLead($link, $customer, ['occurred_at' => now()->subMinutes($i)]);
        }

        DB::enableQueryLog();
        $this->api()->getJson('/api/v1/events?event=leads&limit=25')->assertJsonCount(25, 'data');
        $queries = collect(DB::getQueryLog())->pluck('query')->reject(fn ($q) => str_contains($q, 'workspace_user') || str_contains($q, 'personal_access'));

        $this->assertLessThanOrEqual(4, $queries->count(), $queries->implode("\n")); // events + links + customers (+ workspace)
    }

    public function test_events_are_isolated_between_workspaces(): void
    {
        $this->seedClick(Link::factory()->create(), [], 3);

        $this->api()->getJson('/api/v1/events')->assertJsonCount(0, 'data');
    }

    // ---- export ------------------------------------------------------------------------------

    public function test_exports_events_as_csv(): void
    {
        $this->seedClick($this->link, ['country' => 'DE', 'city' => 'Berlin', 'referer' => 'news.com']);

        $response = $this->api()->get('/api/v1/analytics/export?event=clicks');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('clicks-2026-06-15.csv', $response->headers->get('Content-Disposition'));

        $rows = array_map('str_getcsv', array_filter(explode("\n", $response->streamedContent())));
        $this->assertSame('timestamp', $rows[0][0]);
        $this->assertCount(2, $rows);
        $header = array_flip($rows[0]);
        $this->assertSame('https://shortie.link/main', $rows[1][$header['link']]);
        $this->assertSame('Berlin', $rows[1][$header['city']]);
    }

    public function test_csv_cells_cannot_inject_spreadsheet_formulas(): void
    {
        $this->seedClick($this->link, ['city' => '=HYPERLINK("http://evil.example","click")', 'referer' => '+cmd|calc', 'region' => '@SUM(1)']);

        $csv = $this->api()->get('/api/v1/analytics/export')->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+cmd", $csv);
        $this->assertStringContainsString("'@SUM", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    public function test_export_streams_more_than_one_chunk(): void
    {
        $this->seedClick($this->link, [], 1200);

        $lines = array_filter(explode("\n", $this->api()->get('/api/v1/analytics/export')->streamedContent()));

        $this->assertCount(1201, $lines); // header + 1,200 rows across two 1,000-row chunks
    }

    // ---- customers ---------------------------------------------------------------------------

    public function test_lists_and_searches_customers(): void
    {
        Customer::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'external_id' => 'u1', 'link_id' => $this->link->id, 'created_at' => now()->subDay()]);
        Customer::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Grace Hopper', 'email' => 'grace@navy.mil', 'external_id' => 'u2']);
        Customer::factory()->create(); // other workspace

        $this->api()->getJson('/api/v1/customers')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.name', 'Grace Hopper')->assertJsonPath('meta.hasMore', false);
        $this->api()->getJson('/api/v1/customers?search=lovelace')->assertJsonCount(1, 'data')->assertJsonPath('data.0.link.shortLink', 'https://shortie.link/main');
        $this->api()->getJson('/api/v1/customers?search=navy.mil')->assertJsonCount(1, 'data')->assertJsonPath('data.0.externalId', 'u2');
        $this->api()->getJson('/api/v1/customers?search=u1')->assertJsonCount(1, 'data');
        $this->api()->getJson("/api/v1/customers?linkId={$this->link->id}")->assertJsonCount(1, 'data');
        $this->api()->getJson('/api/v1/customers?pageSize=1')->assertJsonCount(1, 'data')->assertJsonPath('meta.hasMore', true);
    }

    public function test_customer_detail_shows_lifetime_value_and_a_merged_timeline(): void
    {
        $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id, 'link_id' => $this->link->id]);
        $this->seedLead($this->link, $customer, ['occurred_at' => now()->subDays(3)]);
        $this->seedSale($this->link, $customer, 2000, ['occurred_at' => now()->subDays(2)]);
        $this->seedSale($this->link, $customer, 3000, ['occurred_at' => now()->subDay()]);

        $response = $this->api()->getJson("/api/v1/customers/{$customer->id}")->assertOk()->assertJsonPath('lifetimeValue', 5000);

        $this->assertSame(['sale', 'sale', 'lead'], array_column($response->json('activity'), 'type'));
        $this->assertSame(3000, $response->json('activity.0.amount'));
    }

    public function test_customers_of_other_workspaces_are_not_addressable(): void
    {
        $foreign = Customer::factory()->create();

        $this->api()->getJson("/api/v1/customers/{$foreign->id}")->assertNotFound();
    }
}
