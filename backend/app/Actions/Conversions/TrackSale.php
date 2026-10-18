<?php

namespace App\Actions\Conversions;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Link;
use App\Models\Workspace;
use App\Services\ClickLookup;
use App\Services\UsageMeter;
use Illuminate\Support\Facades\DB;

class TrackSale
{
    public function __construct(private ClickLookup $clicks, private UsageMeter $meter) {}

    /**
     * @param  array{customerExternalId: string, amount: int, currency?: string, eventName?: string, paymentProcessor?: string, invoiceId?: ?string, metadata?: ?array, timestamp?: ?string}  $in
     * @return array{customer: Customer, link: Link, created: bool}
     */
    public function __invoke(Workspace $workspace, array $in): array
    {
        $customer = $workspace->customers()->where('external_id', $in['customerExternalId'])->first()
            ?? throw ApiException::notFound('Customer not found. Track a lead for them first.');

        if (! $customer->click_id) {
            throw ApiException::unprocessable('This customer is not attributed to a link, so the sale cannot be attributed.');
        }

        $click = $this->clicks->find($workspace, $customer->click_id);

        return DB::transaction(function () use ($workspace, $in, $customer, $click) {
            $link = $workspace->links()->findOrFail($click->link_id);

            $fresh = ! isset($in['invoiceId']) || DB::table('event_dedupe')->insertOrIgnore([
                'workspace_id' => $workspace->id, 'dedupe_key' => "sale:{$in['invoiceId']}",
            ]) === 1;

            if ($fresh) {
                $this->meter->consumeEvents($workspace);
                DB::table('sale_events')->insert([
                    'occurred_at' => $in['timestamp'] ?? now(),
                    'workspace_id' => $workspace->id,
                    'link_id' => $link->id,
                    'customer_id' => $customer->id,
                    'click_id' => $click->click_id,
                    'event_name' => $in['eventName'] ?? 'Purchase',
                    'invoice_id' => $in['invoiceId'] ?? null,
                    'amount' => $in['amount'],
                    'currency' => strtolower($in['currency'] ?? 'usd'),
                    'payment_processor' => $in['paymentProcessor'] ?? 'custom',
                    'metadata' => isset($in['metadata']) ? json_encode($in['metadata']) : null,
                    ...$this->clicks->dimensions($click),
                ]);
                Link::query()->whereKey($link->id)->update([
                    'sales' => DB::raw('sales + 1'),
                    'sale_amount' => DB::raw('sale_amount + '.(int) $in['amount']),
                ]);
            }

            return ['customer' => $customer, 'link' => $link, 'created' => $fresh];
        });
    }
}
