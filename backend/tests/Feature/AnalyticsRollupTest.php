<?php

namespace Tests\Feature;

use App\Actions\Links\FlushClicks;
use App\Data\AnalyticsFilters;
use App\Models\Customer;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Services\Analytics\AnalyticsQuery;
use App\Services\Analytics\DailyRollup;
use App\Services\ClickBuffer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesWorkspaces;
use Tests\Concerns\SeedsEvents;
use Tests\TestCase;

class AnalyticsRollupTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase, SeedsEvents;

    private $user;

    private $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-15 12:30:45.250000');
        [$this->user, $this->workspace] = $this->ownerWithWorkspace();
    }

    /** Runs $query with the roll-up forced on, then off, and returns both results. */
    private function both(callable $query): array
    {
        config(['shortie.analytics_rollup' => true]);
        $with = $query();
        config(['shortie.analytics_rollup' => false]);
        $without = $query();
        config(['shortie.analytics_rollup' => true]);

        return [$with, $without];
    }

    private function seedMixedHistory(): array
    {
        mt_srand(7);
        $tag = Tag::factory()->create(['workspace_id' => $this->workspace->id]);
        $folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $links = collect(range(1, 4))->map(fn ($i) => Link::factory()->create(['workspace_id' => $this->workspace->id, 'folder_id' => $i > 2 ? $folder->id : null]));
        $links[0]->tags()->attach($tag->id);
        $links[1]->tags()->attach($tag->id);
        $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

        for ($i = 0; $i < 400; $i++) {
            $at = now()->subSeconds(mt_rand(0, 130 * 86400));
            $link = $links[mt_rand(0, 3)];
            $this->seedClick($link, ['occurred_at' => $at, 'is_unique' => mt_rand(0, 1) === 1]);
            if ($i % 5 === 0) {
                $this->seedLead($link, $customer, ['occurred_at' => $at]);
            }
            if ($i % 7 === 0) {
                $this->seedSale($link, $customer, mt_rand(100, 9000), ['occurred_at' => $at]);
            }
        }
        // Boundary stress: exactly on UTC midnights, and one microsecond either side.
        foreach (['2026-06-10 00:00:00', '2026-06-10 23:59:59.999999', '2026-06-11 00:00:00', '2026-06-14 00:00:00'] as $edge) {
            $this->seedClick($links[0], ['occurred_at' => Carbon::parse($edge, 'UTC')]);
            $this->seedSale($links[0], $customer, 100, ['occurred_at' => Carbon::parse($edge, 'UTC')]);
        }

        return [$links, $tag, $folder];
    }

    public function test_roll_up_totals_equal_raw_scans_for_many_ranges_and_scopes(): void
    {
        [$links, $tag, $folder] = $this->seedMixedHistory();
        $ranges = [
            ['interval' => '24h'], ['interval' => '7d'], ['interval' => '30d'], ['interval' => '90d'], ['interval' => '1y'], ['interval' => 'all'],
            ['interval' => 'mtd'], ['interval' => 'qtd'], ['interval' => 'ytd'],
            ['start' => '2026-06-10T00:00:00Z', 'end' => '2026-06-11T00:00:00Z'],   // aligned to midnights, inclusive end
            ['start' => '2026-06-10T00:00:00Z', 'end' => '2026-06-14T00:00:00Z'],
            ['start' => '2026-05-03T07:11:00Z', 'end' => '2026-06-02T19:45:30.123456Z'], // unaligned both ends
            ['start' => '2026-06-14T10:00:00Z', 'end' => '2026-06-14T11:00:00Z'],        // inside a single day
        ];
        $scopes = [[], ['linkId' => $links[0]->id], ['tagIds' => [$tag->id]], ['folderId' => $folder->id], ['domain' => $links[1]->domain, 'key' => $links[1]->key]];
        $query = app(AnalyticsQuery::class);

        foreach ($ranges as $range) {
            foreach ($scopes as $scope) {
                $filters = AnalyticsFilters::fromInput($this->workspace, [...$range, ...$scope]);
                [$with, $without] = $this->both(fn () => $query->totals($this->workspace, $filters));

                $this->assertSame($without, $with, 'totals '.json_encode([$range, $scope]));
            }
        }
    }

    public function test_roll_up_timeseries_equal_raw_scans_for_every_event_type_and_granularity(): void
    {
        [$links] = $this->seedMixedHistory();
        $query = app(AnalyticsQuery::class);

        foreach (['clicks', 'leads', 'sales'] as $event) {
            foreach (['24h', '7d', '30d', '90d', '1y', 'all'] as $interval) {
                foreach ([[], ['linkId' => $links[2]->id]] as $scope) {
                    $filters = AnalyticsFilters::fromInput($this->workspace, ['event' => $event, 'interval' => $interval, ...$scope]);
                    [$with, $without] = $this->both(fn () => $query->timeseries($this->workspace, $filters));

                    $this->assertSame($without, $with, "{$event} {$interval} ".json_encode($scope));
                }
            }
        }
    }

    public function test_boundary_events_are_counted_exactly_once(): void
    {
        $link = Link::factory()->create(['workspace_id' => $this->workspace->id]);
        foreach (['2026-06-09 23:59:59.999999', '2026-06-10 00:00:00', '2026-06-10 12:00:00', '2026-06-11 00:00:00', '2026-06-11 00:00:00.000001'] as $at) {
            $this->seedClick($link, ['occurred_at' => Carbon::parse($at, 'UTC')]);
        }
        $filters = AnalyticsFilters::fromInput($this->workspace, ['start' => '2026-06-10T00:00:00Z', 'end' => '2026-06-11T00:00:00Z']);

        $this->assertSame(3, app(AnalyticsQuery::class)->totals($this->workspace, $filters)['clicks']); // 06-10 00:00, 12:00 and 06-11 00:00 (inclusive end)
    }

    public function test_long_ranges_read_the_roll_up_and_never_scan_the_full_event_range(): void
    {
        $link = Link::factory()->create(['workspace_id' => $this->workspace->id]);
        $this->seedClick($link, ['occurred_at' => now()->subDays(40)], 3);
        DB::enableQueryLog();

        $totals = app(AnalyticsQuery::class)->totals($this->workspace, AnalyticsFilters::fromInput($this->workspace, ['interval' => '90d']));

        $this->assertSame(3, $totals['clicks']);
        $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        $this->assertStringContainsString('analytics_daily', $sql);
        // Raw events are only read for the two partial edge days, each bounded by an explicit time window.
        $this->assertSame(2, substr_count($sql, 'from "click_events"'));
    }

    public function test_falls_back_to_raw_events_when_the_roll_up_cannot_answer_exactly(): void
    {
        $link = Link::factory()->create(['workspace_id' => $this->workspace->id]);
        $this->seedClick($link, ['occurred_at' => now()->subDays(40), 'country' => 'DE'], 2);
        $query = app(AnalyticsQuery::class);

        foreach ([['country' => 'DE'], ['trigger' => 'link'], ['unique' => true], ['device' => 'Desktop']] as $filter) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $result = $query->totals($this->workspace, AnalyticsFilters::fromInput($this->workspace, ['interval' => '90d', ...$filter]));

            $this->assertStringNotContainsString('analytics_daily', collect(DB::getQueryLog())->pluck('query')->implode("\n"), json_encode($filter));
            $this->assertSame(2, $result['clicks'], json_encode($filter));
        }

        // Day buckets in a non-UTC timezone cannot come from UTC-day rows either.
        DB::flushQueryLog();
        $series = $query->timeseries($this->workspace, AnalyticsFilters::fromInput($this->workspace, ['interval' => '90d', 'timezone' => 'Asia/Tokyo']));
        $this->assertStringNotContainsString('analytics_daily', collect(DB::getQueryLog())->pluck('query')->implode("\n"));
        $this->assertSame(2, collect($series)->sum('clicks'));
    }

    // ---- maintenance ------------------------------------------------------------------------

    public function test_increment_accumulates_and_merges_duplicate_keys_in_one_call(): void
    {
        $rollup = app(DailyRollup::class);
        $row = ['workspace_id' => $this->workspace->id, 'link_id' => str_repeat('a', 26), 'day' => '2026-06-01'];

        $rollup->increment([[...$row, 'clicks' => 2, 'unique_clicks' => 1], [...$row, 'clicks' => 3, 'sales' => 1, 'sale_amount' => 500]]);
        $rollup->increment([[...$row, 'clicks' => 5, 'leads' => 2]]);

        $stored = DB::table('analytics_daily')->first();
        $this->assertSame([10, 1, 2, 1, 500], [$stored->clicks, $stored->unique_clicks, $stored->leads, $stored->sales, $stored->sale_amount]);
        $this->assertSame(1, DB::table('analytics_daily')->count());
    }

    public function test_the_write_path_feeds_the_roll_up_and_rebuild_reproduces_it_exactly(): void
    {
        app(ClickBuffer::class)->clear();
        $link = Link::factory()->create(['workspace_id' => $this->workspace->id, 'key' => 'live']);
        foreach (range(1, 6) as $i) {
            $this->flushHeaders()->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/124.0 Safari/537.36', 'X-Test-Visit' => (string) $i])->get('http://shortie.link/live');
        }
        app(FlushClicks::class)();
        $clickId = DB::table('click_events')->value('click_id');
        $this->actingInWorkspace($this->user, $this->workspace)->postJson('/api/v1/track/lead', ['clickId' => $clickId, 'eventName' => 'Sign up', 'customerExternalId' => 'u1'])->assertCreated();
        $this->actingInWorkspace($this->user, $this->workspace)->postJson('/api/v1/track/sale', ['customerExternalId' => 'u1', 'amount' => 1234, 'invoiceId' => 'i1'])->assertCreated();

        $incremental = DB::table('analytics_daily')->orderBy('day')->get()->map(fn ($r) => (array) $r)->all();
        $this->assertSame([6, 0, 1, 1, 1234], [(int) $incremental[0]['clicks'], 0, (int) $incremental[0]['leads'], (int) $incremental[0]['sales'], (int) $incremental[0]['sale_amount']]);
        $this->assertSame(1, (int) $incremental[0]['unique_clicks']); // same visitor (IP + UA) six times → one unique

        $this->artisan('rollup:rebuild')->assertSuccessful();
        $rebuilt = DB::table('analytics_daily')->orderBy('day')->get()->map(fn ($r) => (array) $r)->all();
        $this->assertEquals($incremental, $rebuilt);

        $this->artisan('rollup:rebuild')->assertSuccessful(); // idempotent
        $this->assertEquals($incremental, DB::table('analytics_daily')->orderBy('day')->get()->map(fn ($r) => (array) $r)->all());
        app(ClickBuffer::class)->clear();
    }

    public function test_partial_rebuild_only_touches_the_requested_days_and_repairs_drift(): void
    {
        $link = Link::factory()->create(['workspace_id' => $this->workspace->id]);
        $this->seedClick($link, ['occurred_at' => Carbon::parse('2026-06-01 10:00', 'UTC')], 2);
        $this->seedClick($link, ['occurred_at' => Carbon::parse('2026-06-05 10:00', 'UTC')], 4);
        DB::table('analytics_daily')->where('day', '2026-06-05')->update(['clicks' => 999]);   // corrupt one day
        DB::table('analytics_daily')->where('day', '2026-06-01')->update(['clicks' => 777]);   // and another (outside the rebuild range)

        $this->artisan('rollup:rebuild', ['--from' => '2026-06-04', '--to' => '2026-06-06'])->assertSuccessful();

        $this->assertSame(4, (int) DB::table('analytics_daily')->where('day', '2026-06-05')->value('clicks'));
        $this->assertSame(777, (int) DB::table('analytics_daily')->where('day', '2026-06-01')->value('clicks'));
    }

    public function test_the_roll_up_is_workspace_isolated(): void
    {
        $other = Link::factory()->create();
        $this->seedClick($other, ['occurred_at' => now()->subDays(20)], 9);
        Link::factory()->create(['workspace_id' => $this->workspace->id]);

        $totals = app(AnalyticsQuery::class)->totals($this->workspace, AnalyticsFilters::fromInput($this->workspace, ['interval' => '90d']));

        $this->assertSame(0, $totals['clicks']);
    }
}
