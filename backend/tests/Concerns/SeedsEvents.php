<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\Link;
use App\Services\Analytics\DailyRollup;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Direct, fast inserts into the event tables for analytics assertions. */
trait SeedsEvents
{
    private function eventBase(Link $link, array $o): array
    {
        $at = $o['occurred_at'] ?? now();
        // Keep microseconds: boundary tests rely on them.
        $o['occurred_at'] = $at instanceof CarbonInterface ? $at->format('Y-m-d H:i:s.uP') : $at;

        return [
            'workspace_id' => $link->workspace_id, 'link_id' => $link->id, 'click_id' => Str::lower(Str::random(16)),
            'url' => $link->url, 'country' => 'US', 'continent' => 'NA', 'device' => 'Desktop', 'browser' => 'Chrome',
            'os' => 'Mac', 'referer' => '(direct)', 'qr' => false, ...$o,
        ];
    }

    protected function seedClick(Link $link, array $o = [], int $times = 1): void
    {
        $row = ['is_unique' => true, ...$this->eventBase($link, $o)];
        DB::table('click_events')->insert(array_fill(0, $times, $row));
        $this->rollup($row, ['clicks' => $times, 'unique_clicks' => $row['is_unique'] ? $times : 0]);
    }

    protected function seedLead(Link $link, Customer $customer, array $o = []): void
    {
        $row = ['customer_id' => $customer->id, 'event_name' => 'Sign up', ...$this->eventBase($link, $o)];
        DB::table('lead_events')->insert($row);
        $this->rollup($row, ['leads' => 1]);
    }

    protected function seedSale(Link $link, Customer $customer, int $amount, array $o = []): void
    {
        $row = ['customer_id' => $customer->id, 'event_name' => 'Purchase', 'amount' => $amount, 'currency' => 'usd', 'payment_processor' => 'custom', ...$this->eventBase($link, $o)];
        DB::table('sale_events')->insert($row);
        $this->rollup($row, ['sales' => 1, 'sale_amount' => $amount]);
    }

    /** Mirror what the production write path does, so roll-up-backed reads see seeded events. */
    private function rollup(array $row, array $delta): void
    {
        app(DailyRollup::class)->recordEvent($row['workspace_id'], $row['link_id'], $row['occurred_at'], $delta);
    }
}
