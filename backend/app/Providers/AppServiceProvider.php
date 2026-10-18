<?php

namespace App\Providers;

use App\Services\DnsDomainVerifier;
use App\Services\DomainVerifier;
use App\Support\CurrentWorkspace;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentWorkspace::class);
        $this->app->bind(DomainVerifier::class, DnsDomainVerifier::class);
    }

    public function boot(): void
    {
        // Workspace-scoped route binding: another tenant's id resolves to a 404, never to their data.
        foreach (['link' => 'links', 'tag' => 'tags', 'folder' => 'folders', 'domain' => 'domains', 'template' => 'utmTemplates', 'customer' => 'customers', 'webhook' => 'webhooks'] as $param => $relation) {
            Route::bind($param, fn (string $value) => app(CurrentWorkspace::class)->workspace()->{$relation}()->whereKey($value)->firstOrFail());
        }

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Per-plan API quota, keyed by workspace (falls back to IP before a workspace is resolved).
        RateLimiter::for('api', function (Request $request) {
            $context = app(CurrentWorkspace::class);
            $limit = $context->workspace?->plan->limit('rate_limit') ?? 60;

            return Limit::perMinute($limit)->by($context->workspace?->id ?? $request->ip());
        });
    }
}
