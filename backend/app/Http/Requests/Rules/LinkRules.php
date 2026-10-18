<?php

namespace App\Http\Requests\Rules;

/** Single source of truth for link payload validation (create, update, bulk, upsert). */
final class LinkRules
{
    /** @return array<string, array<int, mixed>> */
    private static function common(): array
    {
        return [
            'url' => ['string', 'max:2048'],
            'domain' => ['nullable', 'string', 'max:255'],
            'key' => ['nullable', 'string', 'max:190'],
            'keyLength' => ['nullable', 'integer', 'min:1', 'max:190'],
            'prefix' => ['nullable', 'string', 'max:100'],
            'externalId' => ['nullable', 'string', 'max:255'],
            'tenantId' => ['nullable', 'string', 'max:255'],
            'trackConversion' => ['boolean'],
            'archived' => ['boolean'],
            'tagIds' => ['nullable', 'array', 'max:50'],
            'tagIds.*' => ['string', 'distinct'],
            'tagNames' => ['nullable', 'array', 'max:50'],
            'tagNames.*' => ['string', 'max:50'],
            'folderId' => ['nullable', 'string'],
            'comments' => ['nullable', 'string', 'max:5000'],
            'expiresAt' => ['nullable', 'date'],
            'expiredUrl' => ['nullable', 'string', 'max:2048'],
            'password' => ['nullable', 'string', 'max:100'],
            'proxy' => ['boolean'],
            'title' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:240'],
            'image' => ['nullable', 'string', 'max:2048'],
            'video' => ['nullable', 'string', 'max:2048'],
            'rewrite' => ['boolean'],
            'ios' => ['nullable', 'string', 'max:2048'],
            'android' => ['nullable', 'string', 'max:2048'],
            'geo' => ['nullable', 'array', 'max:250'],
            'geo.*' => ['string', 'max:2048'],
            'doIndex' => ['boolean'],
            'publicStats' => ['boolean'],
            'utm_source' => ['nullable', 'string', 'max:190'],
            'utm_medium' => ['nullable', 'string', 'max:190'],
            'utm_campaign' => ['nullable', 'string', 'max:190'],
            'utm_term' => ['nullable', 'string', 'max:190'],
            'utm_content' => ['nullable', 'string', 'max:190'],
            'ref' => ['nullable', 'string', 'max:190'],
            'testVariants' => ['nullable', 'array', 'max:4'],
            'testVariants.*.url' => ['required', 'string', 'max:2048'],
            'testVariants.*.percentage' => ['required', 'numeric', 'min:1', 'max:99'],
            'testStartedAt' => ['nullable', 'date'],
            'testCompletedAt' => ['nullable', 'date'],
        ];
    }

    public static function store(): array
    {
        $rules = self::common();
        array_unshift($rules['url'], 'required');

        return $rules;
    }

    public static function update(): array
    {
        return array_map(fn (array $r) => ['sometimes', ...$r], self::common());
    }
}
