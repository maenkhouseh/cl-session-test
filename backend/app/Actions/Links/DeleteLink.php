<?php

namespace App\Actions\Links;

use App\Http\Resources\LinkResource;
use App\Models\Link;
use App\Services\LinkCache;
use App\Services\WebhookDispatcher;

class DeleteLink
{
    public function __construct(private LinkCache $cache, private WebhookDispatcher $webhooks) {}

    public function __invoke(Link $link): void
    {
        $link->loadMissing('tags');
        $link->delete();
        $this->cache->forget($link->domain, $link->key);
        $this->webhooks->dispatch($link->workspace_id, 'link.deleted', fn () => (new LinkResource($link))->resolve());
    }
}
