<?php

namespace App\Actions\Links;

use App\Models\Link;
use App\Models\User;
use App\Models\Workspace;
use App\Services\DomainRegistry;
use App\Support\UrlNormalizer;

/** PUT semantics: reuse the link that already shortens this URL on the domain, else create one. */
class UpsertLink
{
    public function __construct(private CreateLink $create, private UpdateLink $update, private DomainRegistry $domains) {}

    /** @return array{0: Link, 1: bool} link and whether it was newly created */
    public function __invoke(Workspace $workspace, ?User $user, array $input): array
    {
        $domain = strtolower($input['domain'] ?? $this->domains->defaultFor($workspace));
        $url = UrlNormalizer::normalize($input['url']);

        $existing = $workspace->links()->where('domain', $domain)
            ->where(fn ($q) => $q->where('url', $url)->orWhere('url', $input['url']))
            ->orderBy('created_at')->first();

        if ($existing) {
            $changes = collect($input)->except(['url', 'domain', 'key', 'keyLength', 'prefix'])->all();

            return [$changes ? ($this->update)($existing, $changes) : $existing->load('tags'), false];
        }

        return [($this->create)($workspace, $user, $input), true];
    }
}
