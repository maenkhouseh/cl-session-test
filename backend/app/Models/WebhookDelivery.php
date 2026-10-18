<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['webhook_id', 'event', 'payload', 'status', 'response_code', 'response_body', 'attempts'])]
class WebhookDelivery extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
