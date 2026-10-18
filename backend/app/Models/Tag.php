<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['workspace_id', 'name', 'color'])]
class Tag extends Model
{
    use HasFactory, HasUlids;

    public const COLORS = ['red', 'yellow', 'green', 'blue', 'purple', 'pink', 'brown'];

    public function links(): BelongsToMany
    {
        return $this->belongsToMany(Link::class, 'link_tag');
    }
}
