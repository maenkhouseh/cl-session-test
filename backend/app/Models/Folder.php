<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'name', 'description', 'access_level'])]
class Folder extends Model
{
    use HasFactory, HasUlids;

    public const ACCESS_LEVELS = ['write', 'read', 'none'];

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }
}
