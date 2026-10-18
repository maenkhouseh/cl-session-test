<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'email', 'role', 'token_hash', 'expires_at'])]
class WorkspaceInvite extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['role' => WorkspaceRole::class, 'expires_at' => 'datetime'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
