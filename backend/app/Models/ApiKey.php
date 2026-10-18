<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'user_id', 'name', 'key_hash', 'key_preview', 'scopes', 'last_used_at', 'expires_at'])]
#[Hidden(['key_hash'])]
class ApiKey extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['scopes' => 'array', 'last_used_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }
}
