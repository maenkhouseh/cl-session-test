<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function index(Request $request): JsonResponse
    {
        $v = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'linkId' => ['sometimes', 'string'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'pageSize' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $pageSize = (int) ($v['pageSize'] ?? 50);
        $page = (int) ($v['page'] ?? 1);

        $customers = $this->current->workspace()->customers()
            ->when($v['linkId'] ?? null, fn ($q, $id) => $q->where('link_id', $id))
            ->when($v['search'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->whereRaw("(coalesce(name, '') || ' ' || coalesce(email, '')) ilike ?", [$like])->orWhere('external_id', 'ilike', $like);
            })
            ->with('link:id,domain,key')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->offset(($page - 1) * $pageSize)->limit($pageSize + 1)->get();

        return response()->json([
            'data' => $customers->take($pageSize)->map(fn ($c) => $this->present($c))->values(),
            'meta' => ['page' => $page, 'pageSize' => $pageSize, 'hasMore' => $customers->count() > $pageSize],
        ]);
    }

    public function show(Customer $customer): JsonResponse
    {
        $workspace = $this->current->workspace();

        // Two bounded queries build the whole journey; lifetime value is a SUM, not a PHP loop.
        $leads = DB::table('lead_events')->where('workspace_id', $workspace->id)->where('customer_id', $customer->id)
            ->orderByDesc('occurred_at')->limit(100)->get(['occurred_at', 'event_name', 'metadata']);
        $sales = DB::table('sale_events')->where('workspace_id', $workspace->id)->where('customer_id', $customer->id)
            ->orderByDesc('occurred_at')->limit(100)->get(['occurred_at', 'event_name', 'amount', 'currency', 'invoice_id', 'payment_processor']);
        $lifetimeValue = (int) DB::table('sale_events')->where('workspace_id', $workspace->id)->where('customer_id', $customer->id)->sum('amount');

        $activity = $leads->map(fn ($e) => ['type' => 'lead', 'timestamp' => $this->iso($e->occurred_at), 'eventName' => $e->event_name, 'metadata' => $e->metadata ? json_decode($e->metadata, true) : null])
            ->concat($sales->map(fn ($e) => ['type' => 'sale', 'timestamp' => $this->iso($e->occurred_at), 'eventName' => $e->event_name, 'amount' => (int) $e->amount, 'currency' => trim($e->currency), 'invoiceId' => $e->invoice_id]))
            ->sortByDesc('timestamp')->values();

        return response()->json([
            'data' => $this->present($customer->load('link:id,domain,key')),
            'lifetimeValue' => $lifetimeValue,
            'activity' => $activity,
        ]);
    }

    private function present(Customer $c): array
    {
        return [
            'id' => $c->id, 'externalId' => $c->external_id, 'name' => $c->name, 'email' => $c->email, 'avatar' => $c->avatar,
            'country' => $c->country,
            'link' => $c->link ? ['id' => $c->link->id, 'domain' => $c->link->domain, 'key' => $c->link->key, 'shortLink' => $c->link->short_link] : null,
            'createdAt' => $c->created_at,
        ];
    }

    private function iso(string $timestamp): string
    {
        return (new \DateTimeImmutable($timestamp))->format(DATE_ATOM);
    }
}
