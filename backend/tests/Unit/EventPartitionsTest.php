<?php

namespace Tests\Unit;

use App\Services\EventPartitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventPartitionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_is_idempotent_and_creates_future_months(): void
    {
        $service = app(EventPartitions::class);

        $this->assertSame([], $service->ensure('click_events', 3)); // migration already created them
        $created = $service->ensure('click_events', 6);

        $this->assertNotEmpty($created);
        $this->assertSame([], $service->ensure('click_events', 6));
    }

    public function test_rows_route_to_their_month_partition_and_old_rows_to_default(): void
    {
        $row = fn (string $at) => [
            'occurred_at' => $at, 'workspace_id' => str_repeat('0', 26), 'link_id' => str_repeat('1', 26),
            'click_id' => 'abc', 'url' => 'https://a.com',
        ];
        DB::table('click_events')->insert([$row(now()->format('Y-m-15 12:00:00+00')), $row('2001-01-01 00:00:00+00')]);

        $current = 'click_events_'.now()->utc()->format('Y_m');
        $this->assertSame(1, DB::table($current)->count());
        $this->assertSame(1, DB::table('click_events_default')->count());
        $this->assertSame(2, DB::table('click_events')->count());
    }
}
