<?php

namespace App\Providers;

use App\Support\CurrentWorkspace;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentWorkspace::class);
    }

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Per-plan API quota, keyed by workspace (falls back to IP before a workspace is resolved).
        RateLimiter::for('api', function (Request $request) {
            $context = app(CurrentWorkspace::class);
            $limit = $context->workspace?->plan->limit('rate_limit') ?? 60;

            return Limit::perMinute($limit)->by($context->workspace?->id ?? $request->ip());
        });
    }
}
