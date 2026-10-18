<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'name', 'url', 'secret', 'triggers', 'disabled_at', 'consecutive_failures', 'last_failed_at'])]
#[Hidden(['secret'])]
class Webhook extends Model
{
    use HasFactory, HasUlids;

    public const TRIGGERS = ['link.created', 'link.updated', 'link.deleted', 'link.clicked', 'lead.created', 'sale.created'];

    public const MAX_PER_WORKSPACE = 10;

    public const DISABLE_AFTER_FAILURES = 20;

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'triggers' => 'array', 'disabled_at' => 'datetime', 'last_failed_at' => 'datetime'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function isActive(): bool
    {
        return $this->disabled_at === null;
    }
}
