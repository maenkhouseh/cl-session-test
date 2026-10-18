<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Conversions\TrackLead;
use App\Actions\Conversions\TrackSale;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TrackLeadRequest;
use App\Http\Requests\TrackSaleRequest;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;

class TrackController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function lead(TrackLeadRequest $request, TrackLead $track): JsonResponse
    {
        $this->requireConversionTracking();
        $result = $track($this->current->workspace(), $request->validated());

        return response()->json([
            'click' => ['id' => $result['clickId']],
            'link' => $this->link($result['link']),
            'customer' => $this->customer($result['customer']),
        ], $result['created'] ? 201 : 200);
    }

    public function sale(TrackSaleRequest $request, TrackSale $track): JsonResponse
    {
        $this->requireConversionTracking();
        $result = $track($this->current->workspace(), $request->validated());

        return response()->json([
            'eventName' => $request->input('eventName', 'Purchase'),
            'customer' => $this->customer($result['customer']),
            'sale' => [
                'amount' => (int) $request->input('amount'),
                'currency' => strtolower($request->input('currency', 'usd')),
                'paymentProcessor' => $request->input('paymentProcessor', 'custom'),
                'invoiceId' => $request->input('invoiceId'),
            ],
        ], $result['created'] ? 201 : 200);
    }

    private function requireConversionTracking(): void
    {
        if (! $this->current->workspace()->plan->allows('conversion_tracking')) {
            throw ApiException::forbidden('Conversion tracking is not available on your current plan. Upgrade to use it.');
        }
    }

    private function link($link): array
    {
        return ['id' => $link->id, 'domain' => $link->domain, 'key' => $link->key, 'shortLink' => $link->short_link];
    }

    private function customer($customer): array
    {
        return ['id' => $customer->id, 'externalId' => $customer->external_id, 'name' => $customer->name, 'email' => $customer->email, 'avatar' => $customer->avatar];
    }
}
