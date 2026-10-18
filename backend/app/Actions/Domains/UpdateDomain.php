<?php

namespace App\Actions\Domains;

use App\Exceptions\ApiException;
use App\Models\Domain;
use App\Services\LinkCache;
use App\Support\UrlNormalizer;
use Illuminate\Support\Facades\DB;

class UpdateDomain
{
    public function __construct(private LinkCache $cache) {}

    /** @param array{is_primary?: bool, archived?: bool, placeholder_url?: ?string, not_found_url?: ?string, expired_url?: ?string} $data */
    public function __invoke(Domain $domain, array $data): Domain
    {
        foreach (['placeholder_url', 'not_found_url', 'expired_url'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = UrlNormalizer::normalize($data[$field]);
            }
        }

        if (($data['is_primary'] ?? false) && ! $domain->verified) {
            throw ApiException::unprocessable('Only a verified domain can be primary.');
        }

        DB::transaction(function () use ($domain, $data) {
            if ($data['is_primary'] ?? false) {
                $domain->workspace->domains()->whereKeyNot($domain->id)->update(['is_primary' => false]);
            }
            $domain->update($data);
        });

        return $domain;
    }
}
