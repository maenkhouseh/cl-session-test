<?php

namespace App\Http\Resources;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApiKey */
class ApiKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'preview' => $this->key_preview,
            // The full secret is only ever present in the creation response (transient, never persisted).
            'key' => $this->when($this->resource->getAttribute('plain_key') !== null, fn () => $this->resource->getAttribute('plain_key')),
            'lastUsedAt' => $this->last_used_at,
            'expiresAt' => $this->expires_at,
            'createdAt' => $this->created_at,
        ];
    }
}
