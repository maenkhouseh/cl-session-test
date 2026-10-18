<?php

namespace App\Http\Resources;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Workspace */
class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logo,
            'plan' => $this->plan->value,
            'limits' => $this->plan->limits(),
            'usage' => ['links' => $this->links_usage, 'events' => $this->events_usage],
            'usageResetAt' => $this->usage_reset_at,
            // Short domains every workspace can use without verification.
            'systemDomains' => config('shortie.system_domains'),
            'role' => $this->whenPivotLoaded('workspace_user', fn () => $this->pivot->role),
            'createdAt' => $this->created_at,
        ];
    }
}
