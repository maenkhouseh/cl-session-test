<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Models\Customer;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesWorkspaces;
use Tests\Concerns\SeedsEvents;
use Tests\TestCase;

class AnalyticsTest extends TestCase
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

    private function analytics(string $qs)
    {
        return $this->actingInWorkspace($this->user, $this->workspace)->getJson("/api/v1/analytics?{$qs}");
    }

    private function customer(): Customer
    {
        return Customer::factory()->create(['workspace_id' => $this->workspace->id]);
    }

    // ---- totals ------------------------------------------------------------------------------

    public function test_totals_count_each_event_type_in_the_window(): void
    {
        $customer = $this->customer();
        $this->seedClick($this->link, [], 3);
        $this->seedClick($this->link, ['is_unique' => false]);
        $this->seedLead($this->link, $customer);
        $this->seedSale($this->link, $customer, 1000);
        $this->seedSale($this->link, $customer, 2500);

        $this->analytics('groupBy=count&interval=24h')->assertOk()->assertExactJson([
            'clicks' => 4, 'uniqueClicks' => 3, 'leads' => 1, 'sales' => 2, 'saleAmount' => 3500,
        ]);
    }

    public function test_group_by_defaults_to_count_and_an_empty_workspace_reports_zeroes(): void
    {
        $this->analytics('')->assertOk()->assertExactJson(['clicks' => 0, 'uniqueClicks' => 0, 'leads' => 0, 'sales' => 0, 'saleAmount' => 0]);
    }

    public function test_events_outside_the_window_and_in_other_workspaces_are_excluded(): void
    {
        $other = Link::factory()->create();
        $this->seedClick($this->link, ['occurred_at' => now()->subHours(2)]);
        $this->seedClick($this->link, ['occurred_at' => now()->subDays(3)]);
        $this->seedClick($this->link, ['occurred_at' => now()->addMinute()]);
        $this->seedClick($other, [], 5);

        $this->analytics('groupBy=count&interval=24h')->assertJsonPath('clicks', 1);
        $this->analytics('groupBy=count&interval=7d')->assertJsonPath('clicks', 2);
    }

    public function test_every_interval_preset(): void
    {
        foreach ([['occurred_at' => now()->subMinutes(30)], ['occurred_at' => now()->subDays(5)], ['occurred_at' => now()->subDays(20)], ['occurred_at' => now()->subDays(60)],
            ['occurred_at' => now()->subMonths(5)], ['occurred_at' => now()->subYears(2)]] as $o) {
            $this->seedClick($this->link, $o);
        }
        $expected = ['1h' => 1, '24h' => 1, '7d' => 2, '30d' => 3, '90d' => 4, '1y' => 5, 'ytd' => 5, 'mtd' => 2, 'qtd' => 4, 'all' => 6];

        foreach ($expected as $interval => $count) {
            $this->analytics("groupBy=count&interval={$interval}")->assertJsonPath('clicks', $count, "interval {$interval}");
        }
    }

    public function test_custom_start_and_end_override_the_interval(): void
    {
        $this->seedClick($this->link, ['occurred_at' => '2026-06-10 10:00:00+00']);
        $this->seedClick($this->link, ['occurred_at' => '2026-06-12 10:00:00+00']);

        $this->analytics('groupBy=count&start=2026-06-09T00:00:00Z&end=2026-06-11T00:00:00Z')->assertJsonPath('clicks', 1);
    }

    public function test_unique_flag_counts_only_first_visits(): void
    {
        DB::table('click_events')->insert([
            ['is_unique' => true, ...$this->eventRow()],
            ['is_unique' => false, ...$this->eventRow()],
            ['is_unique' => false, ...$this->eventRow()],
        ]);

        $this->analytics('groupBy=count')->assertJsonPath('clicks', 3)->assertJsonPath('uniqueClicks', 1);
        $this->analytics('groupBy=count&unique=true')->assertJsonPath('clicks', 1);
    }

    private function eventRow(array $o = []): array
    {
        return $this->eventBase($this->link, $o);
    }

    public function test_retention_clamps_the_window_to_the_plan(): void
    {
        $this->workspace->update(['plan' => Plan::Free]); // 30-day retention
        $this->seedClick($this->link, ['occurred_at' => now()->subDays(10)]);
        $this->seedClick($this->link, ['occurred_at' => now()->subDays(45)]);

        $this->analytics('groupBy=count&interval=all')->assertJsonPath('clicks', 1);
        $this->analytics('groupBy=count&start=2026-01-01T00:00:00Z')->assertJsonPath('clicks', 1);
    }

    // ---- timeseries --------------------------------------------------------------------------

    public function test_hourly_timeseries_for_short_ranges_is_zero_filled(): void
    {
        $this->seedClick($this->link, ['occurred_at' => '2026-06-15 12:10:00+00'], 2);
        $this->seedClick($this->link, ['occurred_at' => '2026-06-15 09:05:00+00']);

        $series = $this->analytics('groupBy=timeseries&interval=24h')->assertOk()->json();

        $this->assertCount(25, $series); // 12:00 yesterday … 12:00 today, hourly
        $byStart = collect($series)->keyBy('start');
        $this->assertSame(2, $byStart['2026-06-15T12:00:00']['clicks']);
        $this->assertSame(1, $byStart['2026-06-15T09:00:00']['clicks']);
        $this->assertSame(0, $byStart['2026-06-15T10:00:00']['clicks']);
        $this->assertSame(3, collect($series)->sum('clicks'));
    }

    public function test_daily_timeseries_for_medium_ranges(): void
    {
        $this->seedClick($this->link, ['occurred_at' => '2026-06-14 23:30:00+00']);
        $this->seedClick($this->link, ['occurred_at' => '2026-06-10 01:00:00+00'], 4);

        $series = collect($this->analytics('groupBy=timeseries&interval=7d')->json())->keyBy('start');

        $this->assertCount(8, $series);
        $this->assertSame(1, $series['2026-06-14T00:00:00']['clicks']);
        $this->assertSame(4, $series['2026-06-10T00:00:00']['clicks']);
        $this->assertSame(0, $series['2026-06-12T00:00:00']['clicks']);
    }

    public function test_monthly_timeseries_for_long_ranges(): void
    {
        $this->seedClick($this->link, ['occurred_at' => '2026-06-02 00:00:00+00'], 2);
        $this->seedClick($this->link, ['occurred_at' => '2026-03-20 00:00:00+00']);

        $series = collect($this->analytics('groupBy=timeseries&interval=1y')->json())->keyBy('start');

        $this->assertSame(2, $series['2026-06-01T00:00:00']['clicks']);
        $this->assertSame(1, $series['2026-03-01T00:00:00']['clicks']);
        $this->assertSame(0, $series['2026-04-01T00:00:00']['clicks']);
    }

    public function test_timezone_moves_events_across_day_boundaries(): void
    {
        $this->seedClick($this->link, ['occurred_at' => '2026-06-14 23:30:00+00']);

        $utc = collect($this->analytics('groupBy=timeseries&interval=7d&timezone=UTC')->json())->where('clicks', 1)->pluck('start')->all();
        $tokyo = collect($this->analytics('groupBy=timeseries&interval=7d&timezone=Asia/Tokyo')->json())->where('clicks', 1)->pluck('start')->all();

        $this->assertSame(['2026-06-14T00:00:00'], $utc);
        $this->assertSame(['2026-06-15T00:00:00'], $tokyo); // 23:30 UTC is 08:30 next day in Tokyo
    }

    public function test_sales_timeseries_reports_revenue_per_bucket(): void
    {
        $customer = $this->customer();
        $this->seedSale($this->link, $customer, 1000, ['occurred_at' => '2026-06-14 10:00:00+00']);
        $this->seedSale($this->link, $customer, 500, ['occurred_at' => '2026-06-14 11:00:00+00']);

        $day = collect($this->analytics('groupBy=timeseries&interval=7d&event=sales')->json())->firstWhere('start', '2026-06-14T00:00:00');

        $this->assertSame(['sales' => 2, 'saleAmount' => 1500], ['sales' => $day['sales'], 'saleAmount' => $day['saleAmount']]);
    }

    // ---- rankings ----------------------------------------------------------------------------

    public function test_ranks_countries_by_clicks_with_a_stable_tie_break(): void
    {
        $this->seedClick($this->link, ['country' => 'US'], 3);
        $this->seedClick($this->link, ['country' => 'DE'], 2);
        $this->seedClick($this->link, ['country' => 'CA'], 2);

        $this->analytics('groupBy=country&interval=24h')->assertOk()->assertExactJson([
            ['country' => 'US', 'clicks' => 3, 'uniqueClicks' => 3],
            ['country' => 'CA', 'clicks' => 2, 'uniqueClicks' => 2],
            ['country' => 'DE', 'clicks' => 2, 'uniqueClicks' => 2],
        ]);
    }

    public function test_every_dimension_can_be_grouped(): void
    {
        $this->seedClick($this->link, ['region' => 'CA', 'city' => 'SF', 'continent' => 'NA', 'device' => 'Mobile', 'browser' => 'Safari', 'os' => 'iOS',
            'referer' => 'google.com', 'referer_url' => 'https://google.com/q', 'utm_source' => 'g', 'utm_medium' => 'cpc', 'utm_campaign' => 'c', 'utm_term' => 't', 'utm_content' => 'x', 'qr' => true]);
        $expected = ['country' => 'US', 'region' => 'CA', 'city' => 'SF', 'continent' => 'NA', 'device' => 'Mobile', 'browser' => 'Safari', 'os' => 'iOS',
            'referer' => 'google.com', 'refererUrl' => 'https://google.com/q', 'utm_source' => 'g', 'utm_medium' => 'cpc', 'utm_campaign' => 'c', 'utm_term' => 't', 'utm_content' => 'x', 'trigger' => 'qr'];

        foreach ($expected as $groupBy => $value) {
            $this->analytics("groupBy={$groupBy}")->assertOk()->assertJsonPath('0.'.$groupBy, $value)->assertJsonPath('0.clicks', 1);
        }
        $this->analytics('groupBy=top_urls')->assertJsonPath('0.url', $this->link->url);
    }

    public function test_top_links_include_link_details_with_a_single_extra_query(): void
    {
        $other = Link::factory()->create(['workspace_id' => $this->workspace->id, 'key' => 'second']);
        $this->seedClick($this->link, [], 3);
        $this->seedClick($other, [], 1);

        DB::enableQueryLog();
        $response = $this->analytics('groupBy=top_links')->assertOk();
        $linkQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "links"'));

        $response->assertJsonPath('0.link.key', 'main')->assertJsonPath('0.clicks', 3)->assertJsonPath('1.link.key', 'second')
            ->assertJsonPath('0.link.shortLink', 'https://shortie.link/main');
        $this->assertCount(1, $linkQueries);
    }

    public function test_top_links_survive_deleted_links(): void
    {
        $doomed = Link::factory()->create(['workspace_id' => $this->workspace->id]);
        $this->seedClick($doomed, [], 2);
        $doomed->delete();

        $this->analytics('groupBy=top_links')->assertOk()->assertJsonPath('0.link.deleted', true)->assertJsonPath('0.clicks', 2);
    }

    public function test_sales_rankings_report_revenue(): void
    {
        $customer = $this->customer();
        $this->seedSale($this->link, $customer, 5000, ['country' => 'DE']);
        $this->seedSale($this->link, $customer, 1000, ['country' => 'DE']);
        $this->seedSale($this->link, $customer, 9000, ['country' => 'US']);

        $this->analytics('groupBy=country&event=sales')->assertExactJson([
            ['country' => 'DE', 'sales' => 2, 'saleAmount' => 6000],
            ['country' => 'US', 'sales' => 1, 'saleAmount' => 9000],
        ]);
    }

    // ---- filters -----------------------------------------------------------------------------

    public function test_dimension_filters_support_multiple_values_and_negation(): void
    {
        $this->seedClick($this->link, ['country' => 'US', 'device' => 'Desktop'], 3);
        $this->seedClick($this->link, ['country' => 'DE', 'device' => 'Mobile'], 2);
        $this->seedClick($this->link, ['country' => 'FR', 'device' => 'Mobile'], 1);

        $this->analytics('groupBy=count&country=US')->assertJsonPath('clicks', 3);
        $this->analytics('groupBy=count&country=US,DE')->assertJsonPath('clicks', 5);
        $this->analytics('groupBy=count&country=-US')->assertJsonPath('clicks', 3);
        $this->analytics('groupBy=count&country=-US,-DE')->assertJsonPath('clicks', 1);
        $this->analytics('groupBy=count&country=DE&device=Mobile')->assertJsonPath('clicks', 2);
        $this->analytics('groupBy=count&country=DE&device=Desktop')->assertJsonPath('clicks', 0);
    }

    public function test_negated_filters_keep_rows_where_the_dimension_is_unknown(): void
    {
        $this->seedClick($this->link, ['country' => null], 2);
        $this->seedClick($this->link, ['country' => 'US'], 1);

        $this->analytics('groupBy=count&country=-US')->assertJsonPath('clicks', 2);
    }

    public function test_filters_by_link_by_domain_and_key_by_tag_and_by_folder(): void
    {
        $folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $tag = Tag::factory()->create(['workspace_id' => $this->workspace->id]);
        $tagged = Link::factory()->create(['workspace_id' => $this->workspace->id, 'key' => 'tagged', 'folder_id' => $folder->id]);
        $tagged->tags()->attach($tag->id);
        $this->seedClick($this->link, [], 5);
        $this->seedClick($tagged, [], 2);

        $this->analytics("groupBy=count&linkId={$tagged->id}")->assertJsonPath('clicks', 2);
        $this->analytics('groupBy=count&domain=shortie.link&key=MAIN')->assertJsonPath('clicks', 5);
        $this->analytics("groupBy=count&tagIds[]={$tag->id}")->assertJsonPath('clicks', 2);
        $this->analytics("groupBy=count&tagIds={$tag->id}")->assertJsonPath('clicks', 2);
        $this->analytics("groupBy=count&folderId={$folder->id}")->assertJsonPath('clicks', 2);
        $this->analytics('groupBy=count&domain=shortie.link&key=ghost')->assertJsonPath('clicks', 0);
    }

    public function test_another_workspaces_link_id_yields_nothing(): void
    {
        $foreign = Link::factory()->create();
        $this->seedClick($foreign, [], 4);

        $this->analytics("groupBy=count&linkId={$foreign->id}")->assertJsonPath('clicks', 0);
    }

    public function test_trigger_filter(): void
    {
        $this->seedClick($this->link, ['qr' => true], 2);
        $this->seedClick($this->link, ['qr' => false], 5);

        $this->analytics('groupBy=count&trigger=qr')->assertJsonPath('clicks', 2);
        $this->analytics('groupBy=count&trigger=link')->assertJsonPath('clicks', 5);
    }

    public function test_conversions_are_filterable_by_the_clicks_dimensions(): void
    {
        $customer = $this->customer();
        $this->seedLead($this->link, $customer, ['country' => 'DE', 'utm_source' => 'x']);
        $this->seedLead($this->link, $customer, ['country' => 'US']);

        $this->analytics('groupBy=count&country=DE')->assertJsonPath('leads', 1);
        $this->analytics('groupBy=count&utm_source=x')->assertJsonPath('leads', 1);
    }

    // ---- guard rails -------------------------------------------------------------------------

    public function test_invalid_parameters_are_rejected(): void
    {
        foreach (['groupBy=nonsense', 'interval=forever', 'timezone=Mars/Base', 'event=impressions', 'start=2026-06-10&end=2026-06-01', 'domain=x.com', 'key=abc', 'limit=0'] as $qs) {
            $this->analytics($qs)->assertStatus(422);
        }
    }

    public function test_requires_authentication_and_a_workspace(): void
    {
        $this->getJson('/api/v1/analytics')->assertUnauthorized();
        $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/analytics')->assertStatus(400);
    }

    public function test_totals_use_one_events_query_regardless_of_data_volume(): void
    {
        $this->seedClick($this->link, [], 50);
        DB::enableQueryLog();

        $this->analytics('groupBy=count')->assertJsonPath('clicks', 50);

        $eventQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'click_events') || str_contains($q['query'], 'lead_events') || str_contains($q['query'], 'sale_events'));
        $this->assertCount(1, $eventQueries); // clicks + leads + sales in a single UNION ALL
    }
}
