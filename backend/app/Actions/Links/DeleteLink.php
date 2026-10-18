<?php

namespace App\Actions\Links;

use App\Models\Link;
use App\Services\LinkCache;

class DeleteLink
{
    public function __construct(private LinkCache $cache) {}

    public function __invoke(Link $link): void
    {
        $link->delete();
        $this->cache->forget($link->domain, $link->key);
    }
}
