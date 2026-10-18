<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'link_id', 'password', 'do_index'])]
#[Hidden(['password'])]
class SharedDashboard extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['do_index' => 'boolean'];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }

    /** Public identifier used in share URLs. */
    public function getTokenAttribute(): string
    {
        return 'dash_'.$this->id;
    }
}
