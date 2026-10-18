<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'slug', 'verified', 'verified_at', 'is_primary', 'archived', 'placeholder_url', 'not_found_url', 'expired_url'])]
class Domain extends Model
{
    use HasFactory, HasUlids;

    /** Mirror the column defaults so a freshly created model serialises without a refresh query. */
    protected $attributes = ['verified' => false, 'is_primary' => false, 'archived' => false];

    protected function casts(): array
    {
        return ['verified' => 'boolean', 'is_primary' => 'boolean', 'archived' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
