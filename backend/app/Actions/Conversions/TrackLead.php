<?php

namespace App\Actions\Conversions;

use App\Models\Customer;
use App\Models\Link;
use App\Models\Workspace;
use App\Services\ClickLookup;
use App\Services\UsageMeter;
use Illuminate\Support\Facades\DB;

class TrackLead
{
    public function __construct(private ClickLookup $clicks, private UsageMeter $meter) {}

    /**
     * @param  array{clickId: string, eventName: string, customerExternalId: string, customerName?: ?string, customerEmail?: ?string, customerAvatar?: ?string, metadata?: ?array, timestamp?: ?string}  $in
     * @return array{customer: Customer, link: Link, clickId: string, created: bool}
     */
    public function __invoke(Workspace $workspace, array $in): array
    {
        $click = $this->clicks->find($workspace, $in['clickId']);
        $dedupe = "lead:{$in['customerExternalId']}:{$in['eventName']}";

        return DB::transaction(function () use ($workspace, $in, $click, $dedupe) {
            $customer = $this->upsertCustomer($workspace, $in, $click);
            $link = $workspace->links()->findOrFail($click->link_id);

            // Same customer + event name = same lead; replays are harmless.
            $fresh = DB::table('event_dedupe')->insertOrIgnore(['workspace_id' => $workspace->id, 'dedupe_key' => $dedupe]) === 1;

            if ($fresh) {
                $this->meter->consumeEvents($workspace);
                DB::table('lead_events')->insert([
                    'occurred_at' => $in['timestamp'] ?? now(),
                    'workspace_id' => $workspace->id,
                    'link_id' => $link->id,
                    'customer_id' => $customer->id,
                    'click_id' => $click->click_id,
                    'event_name' => $in['eventName'],
                    'metadata' => isset($in['metadata']) ? json_encode($in['metadata']) : null,
                    ...$this->clicks->dimensions($click),
                ]);
                Link::query()->whereKey($link->id)->update(['leads' => DB::raw('leads + 1')]);
            }

            return ['customer' => $customer, 'link' => $link, 'clickId' => $click->click_id, 'created' => $fresh];
        });
    }

    private function upsertCustomer(Workspace $workspace, array $in, object $click): Customer
    {
        $profile = array_filter([
            'name' => $in['customerName'] ?? null,
            'email' => isset($in['customerEmail']) ? strtolower($in['customerEmail']) : null,
            'avatar' => $in['customerAvatar'] ?? null,
        ], fn ($v) => $v !== null);

        $customer = Customer::query()->firstOrNew(['workspace_id' => $workspace->id, 'external_id' => $in['customerExternalId']]);

        if (! $customer->exists) {
            $customer->fill([...$profile, 'link_id' => $click->link_id, 'click_id' => $click->click_id, 'country' => $click->country ? trim($click->country) : null]);
        } else {
            $customer->fill($profile); // first-touch attribution is never overwritten
        }

        $customer->save();

        return $customer;
    }
}
