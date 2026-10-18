<?php

namespace App\Actions\Links;

use App\Exceptions\ApiException;
use App\Http\Resources\LinkResource;
use App\Models\Link;
use App\Models\User;
use App\Models\Workspace;
use App\Services\DomainRegistry;
use App\Services\KeyGenerator;
use App\Services\LinkAttributes;
use App\Services\LinkCache;
use App\Services\LinkRelations;
use App\Services\UsageMeter;
use App\Services\WebhookDispatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateLink
{
    public function __construct(
        private LinkAttributes $attributes,
        private LinkRelations $relations,
        private DomainRegistry $domains,
        private KeyGenerator $keys,
        private UsageMeter $meter,
        private LinkCache $cache,
        private WebhookDispatcher $webhooks,
    ) {}

    /** @param array<string, mixed> $input camelCase API input */
    public function __invoke(Workspace $workspace, ?User $user, array $input): Link
    {
        $attributes = $this->attributes->prepare($workspace, $input);
        $domain = $this->domains->assertUsable($workspace, $input['domain'] ?? $this->domains->defaultFor($workspace));
        $tagIds = $this->relations->tagIds($workspace, $input['tagIds'] ?? null, $input['tagNames'] ?? null);
        $folderId = $this->relations->folderId($workspace, $attributes['folder_id'] ?? null);

        $this->assertExternalIdFree($workspace, $attributes['external_id'] ?? null);
        $key = $this->resolveKey($domain, $input);

        try {
            $link = DB::transaction(function () use ($workspace, $user, $attributes, $domain, $key, $folderId, $tagIds) {
                $this->meter->consumeLink($workspace);

                $link = Link::create([
                    ...$attributes,
                    'workspace_id' => $workspace->id,
                    'user_id' => $user?->id,
                    'folder_id' => $folderId,
                    'domain' => $domain,
                    'key' => $key,
                ]);
                $link->tags()->attach($tagIds ?? []);

                return $link;
            });
        } catch (UniqueConstraintViolationException) {
            throw ApiException::conflict('Duplicate key: this short link already exists.');
        }

        $this->cache->forget($domain, $key);
        $link->load('tags');
        $this->webhooks->dispatch($workspace, 'link.created', fn () => (new LinkResource($link))->resolve());

        return $link;
    }

    private function resolveKey(string $domain, array $input): string
    {
        if (isset($input['key']) && $input['key'] !== '') {
            $key = $input['key'];

            if (! $this->keys->isValid($key) || $this->keys->isReserved($key)) {
                throw ApiException::unprocessable("The key \"{$key}\" is invalid or reserved.");
            }

            if (Link::query()->forDomainKey($domain, $key)->exists()) {
                throw ApiException::conflict('Duplicate key: this short link already exists.');
            }

            return $key;
        }

        return $this->keys->generate($domain, (int) ($input['keyLength'] ?? 7), $input['prefix'] ?? '');
    }

    private function assertExternalIdFree(Workspace $workspace, ?string $externalId): void
    {
        if ($externalId !== null && $workspace->links()->where('external_id', $externalId)->exists()) {
            throw ApiException::conflict("A link with externalId \"{$externalId}\" already exists.");
        }
    }
}
