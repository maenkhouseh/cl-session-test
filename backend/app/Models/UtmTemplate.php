<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['workspace_id', 'name', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ref'])]
class UtmTemplate extends Model
{
    use HasFactory, HasUlids;
}
