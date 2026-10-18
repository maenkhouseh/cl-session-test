<?php

namespace App\Http\Resources;

use App\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Webhook */
class WebhookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'url' => $this->url, 'triggers' => $this->triggers,
            'disabled' => $this->disabled_at !== null, 'disabledAt' => $this->disabled_at,
            'consecutiveFailures' => $this->consecutive_failures, 'lastFailedAt' => $this->last_failed_at,
            // The signing secret is only ever returned from the creation response.
            'secret' => $this->when($this->resource->getAttribute('plain_secret') !== null, fn () => $this->resource->getAttribute('plain_secret')),
            'createdAt' => $this->created_at,
        ];
    }
}
