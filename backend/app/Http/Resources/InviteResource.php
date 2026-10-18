<?php

namespace App\Http\Resources;

use App\Models\WorkspaceInvite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkspaceInvite */
class InviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role->value,
            'expiresAt' => $this->expires_at,
            'createdAt' => $this->created_at,
        ];
    }
}
