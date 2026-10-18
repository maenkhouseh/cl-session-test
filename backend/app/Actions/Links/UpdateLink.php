<?php

namespace App\Actions\Links;

use App\Exceptions\ApiException;
use App\Models\Link;
use App\Services\DomainRegistry;
use App\Services\KeyGenerator;
use App\Services\LinkAttributes;
use App\Services\LinkCache;
use App\Services\LinkRelations;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class UpdateLink
{
    public function __construct(
        private LinkAttributes $attributes,
        private LinkRelations $relations,
        private DomainRegistry $domains,
        private KeyGenerator $keys,
        private LinkCache $cache,
    ) {}

    /** @param array<string, mixed> $input only the keys the caller wants to change */
    public function __invoke(Link $link, array $input): Link
    {
        $workspace = $link->workspace;
        $attributes = $this->attributes->prepare($workspace, $input, $link);
        $tagIds = $this->relations->tagIds($workspace, $input['tagIds'] ?? null, $input['tagNames'] ?? null);

        if (array_key_exists('folder_id', $attributes)) {
            $attributes['folder_id'] = $this->relations->folderId($workspace, $attributes['folder_id']);
        }

        if (isset($attributes['external_id']) && $attributes['external_id'] !== $link->external_id
            && $workspace->links()->where('external_id', $attributes['external_id'])->exists()) {
            throw ApiException::conflict("A link with externalId \"{$attributes['external_id']}\" already exists.");
        }

        $this->guardIdentityChange($link, $input);

        try {
            DB::transaction(function () use ($link, $attributes, $input, $tagIds) {
                $link->fill([
                    ...$attributes,
                    ...(isset($input['domain']) ? ['domain' => strtolower($input['domain'])] : []),
                    ...(isset($input['key']) ? ['key' => $input['key']] : []),
                ])->save();

                if ($tagIds !== null) {
                    $link->tags()->sync($tagIds);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ApiException::conflict('Duplicate key: this short link already exists.');
        }

        $this->cache->forgetLink($link);

        return $link->load('tags');
    }

    private function guardIdentityChange(Link $link, array $input): void
    {
        $domain = isset($input['domain']) ? strtolower($input['domain']) : $link->domain;
        $key = $input['key'] ?? $link->key;

        if ($domain === $link->domain && strtolower($key) === strtolower($link->key)) {
            return;
        }

        $this->domains->assertUsable($link->workspace, $domain);

        if (! $this->keys->isValid($key) || $this->keys->isReserved($key)) {
            throw ApiException::unprocessable("The key \"{$key}\" is invalid or reserved.");
        }

        if (Link::query()->forDomainKey($domain, $key)->whereKeyNot($link->id)->exists()) {
            throw ApiException::conflict('Duplicate key: this short link already exists.');
        }
    }
}
