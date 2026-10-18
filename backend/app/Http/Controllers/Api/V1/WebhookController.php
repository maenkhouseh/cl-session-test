<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Webhooks\SaveWebhook;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveWebhookRequest;
use App\Http\Resources\WebhookResource;
use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Services\WebhookDispatcher;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function index(): AnonymousResourceCollection
    {
        return WebhookResource::collection($this->current->workspace()->webhooks()->latest()->get());
    }

    public function store(SaveWebhookRequest $request, SaveWebhook $save): JsonResponse
    {
        [$webhook, $secret] = $save($this->current->workspace(), $request->validated());

        return (new WebhookResource($webhook->setAttribute('plain_secret', $secret)))->response()->setStatusCode(201);
    }

    public function update(SaveWebhookRequest $request, Webhook $webhook, SaveWebhook $save): WebhookResource
    {
        [$webhook] = $save($this->current->workspace(), $request->validated(), $webhook);

        return new WebhookResource($webhook);
    }

    public function destroy(Webhook $webhook, WebhookDispatcher $dispatcher): JsonResponse
    {
        $webhook->delete();
        $dispatcher->forget($this->current->workspace()->id);

        return response()->json(['id' => $webhook->id]);
    }

    public function deliveries(Webhook $webhook): JsonResponse
    {
        $deliveries = $webhook->deliveries()->latest('created_at')->limit(50)->get(['id', 'event', 'status', 'response_code', 'attempts', 'created_at']);

        return response()->json(['data' => $deliveries]);
    }

    /** Sends a sample payload so integrators can verify their endpoint + signature handling. */
    public function test(Webhook $webhook): JsonResponse
    {
        DeliverWebhook::dispatch($webhook->id, 'webhook.test', [
            'id' => 'evt_'.Str::lower((string) Str::ulid()), 'event' => 'webhook.test', 'createdAt' => now()->toIso8601String(),
            'data' => ['message' => 'This is a test event from '.config('app.name').'.'],
        ]);

        return response()->json(['queued' => true], 202);
    }
}
