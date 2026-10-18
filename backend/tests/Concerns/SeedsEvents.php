<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\Link;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Direct, fast inserts into the event tables for analytics assertions. */
trait SeedsEvents
{
    private function eventBase(Link $link, array $o): array
    {
        $at = $o['occurred_at'] ?? now();
        $o['occurred_at'] = $at instanceof CarbonInterface ? $at->toIso8601String() : $at;

        return [
            'workspace_id' => $link->workspace_id, 'link_id' => $link->id, 'click_id' => Str::lower(Str::random(16)),
            'url' => $link->url, 'country' => 'US', 'continent' => 'NA', 'device' => 'Desktop', 'browser' => 'Chrome',
            'os' => 'Mac', 'referer' => '(direct)', 'qr' => false, ...$o,
        ];
    }

    protected function seedClick(Link $link, array $o = [], int $times = 1): void
    {
        DB::table('click_events')->insert(array_fill(0, $times, ['is_unique' => true, ...$this->eventBase($link, $o)]));
    }

    protected function seedLead(Link $link, Customer $customer, array $o = []): void
    {
        DB::table('lead_events')->insert(['customer_id' => $customer->id, 'event_name' => 'Sign up', ...$this->eventBase($link, $o)]);
    }

    protected function seedSale(Link $link, Customer $customer, int $amount, array $o = []): void
    {
        DB::table('sale_events')->insert(['customer_id' => $customer->id, 'event_name' => 'Purchase', 'amount' => $amount, 'currency' => 'usd', 'payment_processor' => 'custom', ...$this->eventBase($link, $o)]);
    }
}
