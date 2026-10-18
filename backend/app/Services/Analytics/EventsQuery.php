<?php

namespace App\Services\Analytics;

use App\Data\AnalyticsFilters;
use App\Models\Customer;
use App\Models\Link;
use App\Models\Workspace;
use Generator;

/** Event-level listing with keyset pagination (stable under concurrent inserts, O(page) at any depth). */
class EventsQuery
{
    public function __construct(private AnalyticsQuery $analytics) {}

    /** @return array{rows: array<int, array<string, mixed>>, nextCursor: ?string} */
    public function page(Workspace $workspace, AnalyticsFilters $f, ?string $cursor, int $limit = 50): array
    {
        $q = $this->analytics->scoped($workspace, $f, $f->event);
        if (! $q) {
            return ['rows' => [], 'nextCursor' => null];
        }

        $table = AnalyticsQuery::TABLES[$f->event];

        if ($cursor && ($decoded = $this->decode($cursor))) {
            [$at, $id] = $decoded;
            $q->whereRaw("({$table}.occurred_at, {$table}.id) < (?, ?)", [$at, $id]);
        }

        $rows = $q->orderByDesc("{$table}.occurred_at")->orderByDesc("{$table}.id")->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $links = Link::query()->whereIn('id', $rows->pluck('link_id')->map(fn ($id) => trim($id))->unique())->get(['id', 'domain', 'key', 'url'])->keyBy('id');
        $customers = $f->event === 'clicks' ? collect() : Customer::query()->whereIn('id', $rows->pluck('customer_id')->map(fn ($id) => trim($id))->unique())->get(['id', 'external_id', 'name', 'email', 'avatar'])->keyBy('id');

        $last = $rows->last();

        return [
            'rows' => $rows->map(fn ($r) => $this->present($f->event, $r, $links, $customers))->values()->all(),
            'nextCursor' => $hasMore && $last ? $this->encode($last) : null,
        ];
    }

    /** Streams every matching event (for CSV export) in keyset-ordered chunks. @return Generator<int, array<string, mixed>> */
    public function stream(Workspace $workspace, AnalyticsFilters $f, int $max = 100_000): Generator
    {
        $cursor = null;
        $emitted = 0;

        do {
            $page = $this->page($workspace, $f, $cursor, 1000);
            foreach ($page['rows'] as $row) {
                if ($emitted++ >= $max) {
                    return;
                }
                yield $row;
            }
            $cursor = $page['nextCursor'];
        } while ($cursor);
    }

    private function present(string $event, object $r, $links, $customers): array
    {
        $link = $links->get(trim($r->link_id));
        $customer = isset($r->customer_id) ? $customers->get(trim($r->customer_id)) : null;

        return array_filter([
            'event' => rtrim($event, 's'),
            'timestamp' => (new \DateTimeImmutable($r->occurred_at))->format(DATE_ATOM),
            'eventName' => $r->event_name ?? null,
            'clickId' => $r->click_id,
            'link' => $link ? ['id' => $link->id, 'domain' => $link->domain, 'key' => $link->key, 'shortLink' => $link->short_link] : null,
            'customer' => $customer ? ['id' => $customer->id, 'externalId' => $customer->external_id, 'name' => $customer->name, 'email' => $customer->email, 'avatar' => $customer->avatar] : null,
            'url' => $r->url,
            'country' => $r->country ? trim($r->country) : null,
            'region' => $r->region, 'city' => $r->city, 'continent' => $r->continent ? trim($r->continent) : null,
            'device' => $r->device, 'browser' => $r->browser, 'os' => $r->os,
            'referer' => $r->referer, 'refererUrl' => $r->referer_url,
            'qr' => (bool) $r->qr,
            'isUnique' => isset($r->is_unique) ? (bool) $r->is_unique : null,
            'amount' => $r->amount ?? null, 'currency' => isset($r->currency) ? trim($r->currency) : null,
            'invoiceId' => $r->invoice_id ?? null,
            'utm_source' => $r->utm_source, 'utm_medium' => $r->utm_medium, 'utm_campaign' => $r->utm_campaign,
            'utm_term' => $r->utm_term, 'utm_content' => $r->utm_content,
        ], fn ($v) => $v !== null);
    }

    private function encode(object $row): string
    {
        return rtrim(strtr(base64_encode(json_encode([$row->occurred_at, $row->id])), '+/', '-_'), '=');
    }

    /** @return ?array{0: string, 1: int} */
    private function decode(string $cursor): ?array
    {
        $data = json_decode((string) base64_decode(strtr($cursor, '-_', '+/')), true);

        return is_array($data) && count($data) === 2 && is_string($data[0]) && is_int($data[1]) ? $data : null;
    }
}
