<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'workspace_id', 'user_id', 'folder_id', 'domain', 'key', 'url', 'external_id', 'tenant_id',
    'track_conversion', 'archived', 'expires_at', 'expired_url', 'disabled_at', 'password', 'proxy', 'rewrite',
    'do_index', 'public_stats', 'title', 'description', 'image', 'video', 'ios', 'android', 'geo', 'comments',
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ref',
    'test_variants', 'test_started_at', 'test_completed_at',
    'clicks', 'leads', 'sales', 'sale_amount', 'last_clicked_at',
])]
#[Hidden(['password'])]
class Link extends Model
{
    use HasFactory, HasUlids;

    /** Mirror the column defaults so a freshly created model serialises without a refresh query. */
    protected $attributes = [
        'track_conversion' => false, 'archived' => false, 'proxy' => false, 'rewrite' => false,
        'do_index' => false, 'public_stats' => false,
        'clicks' => 0, 'leads' => 0, 'sales' => 0, 'sale_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'track_conversion' => 'boolean', 'archived' => 'boolean', 'proxy' => 'boolean', 'rewrite' => 'boolean',
            'do_index' => 'boolean', 'public_stats' => 'boolean',
            'geo' => 'array', 'test_variants' => 'array',
            'expires_at' => 'datetime', 'disabled_at' => 'datetime', 'last_clicked_at' => 'datetime',
            'test_started_at' => 'datetime', 'test_completed_at' => 'datetime',
            'clicks' => 'integer', 'leads' => 'integer', 'sales' => 'integer', 'sale_amount' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'link_tag')->select(['tags.id', 'tags.name', 'tags.color']);
    }

    public function scopeForDomainKey(Builder $query, string $domain, string $key): Builder
    {
        return $query->where('domain', strtolower($domain))->whereRaw('lower(key) = ?', [strtolower($key)]);
    }

    public function getShortLinkAttribute(): string
    {
        return "https://{$this->domain}/{$this->key}";
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }
}
