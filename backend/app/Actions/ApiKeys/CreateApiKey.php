<?php

namespace App\Actions\ApiKeys;

use App\Models\ApiKey;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Hash;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class CreateApiKey
{
    /** @return array{0: ApiKey, 1: string} model and the plain key (returned to the caller once) */
    public function __invoke(Workspace $workspace, User $user, string $name, ?array $scopes = null, ?CarbonInterface $expiresAt = null): array
    {
        $plain = config('shortie.api_key_prefix').Str::random(40);

        $key = $workspace->apiKeys()->create([
            'user_id' => $user->id,
            'name' => $name,
            'key_hash' => Hash::secret($plain),
            'key_preview' => substr($plain, 0, 9).'…'.substr($plain, -4),
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
        ]);

        return [$key, $plain];
    }
}
