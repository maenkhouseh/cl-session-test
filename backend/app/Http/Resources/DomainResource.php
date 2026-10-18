<?php

namespace App\Http\Resources;

use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Domain */
class DomainResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'slug' => $this->slug, 'verified' => $this->verified, 'verifiedAt' => $this->verified_at,
            'primary' => $this->is_primary, 'archived' => $this->archived,
            'placeholder' => $this->placeholder_url, 'notFoundUrl' => $this->not_found_url, 'expiredUrl' => $this->expired_url,
            // DNS instructions the dashboard shows until the domain verifies.
            'dns' => ['type' => 'CNAME', 'name' => $this->slug, 'value' => config('shortie.dns_target')],
            'createdAt' => $this->created_at, 'updatedAt' => $this->updated_at,
        ];
    }
}
