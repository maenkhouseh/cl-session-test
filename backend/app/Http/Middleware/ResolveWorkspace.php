<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\ApiKey;
use App\Support\CurrentWorkspace;
use App\Support\Hash;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the acting user + workspace for tenant-scoped routes.
 *  - `Authorization: Bearer shrt_…`  → workspace is implied by the API key.
 *  - Sanctum session / token         → workspace chosen via `X-Workspace` header or `?workspace=` (slug or id).
 */
class ResolveWorkspace
{
    public function __construct(private CurrentWorkspace $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token && str_starts_with($token, config('shortie.api_key_prefix'))) {
            $this->resolveFromApiKey($token);
        } else {
            $this->resolveFromSession($request);
        }

        return $next($request);
    }

    private function resolveFromApiKey(string $token): void
    {
        $key = ApiKey::query()->with(['workspace', 'user'])->where('key_hash', Hash::secret($token))->first();

        if (! $key || $key->isExpired()) {
            throw new AuthenticationException;
        }

        // Avoid a write on every request: touch at most once a minute.
        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        Auth::setUser($key->user);
        $this->context->set($key->workspace, $key->user, $key);
    }

    private function resolveFromSession(Request $request): void
    {
        $user = $request->user('sanctum') ?? throw new AuthenticationException;
        $identifier = $request->header('X-Workspace') ?: $request->query('workspace');

        if (! $identifier) {
            throw ApiException::badRequest('Specify a workspace via the X-Workspace header.');
        }

        $workspace = $user->workspaces()
            ->where(fn ($q) => $q->where('workspaces.slug', $identifier)->orWhere('workspaces.id', $identifier))
            ->first() ?? throw ApiException::notFound('Workspace not found.');

        Auth::setUser($user);
        $this->context->set($workspace, $user);
    }
}
