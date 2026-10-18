<?php

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\ApiKeyController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BulkLinkController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DomainController;
use App\Http\Controllers\Api\V1\FolderController;
use App\Http\Controllers\Api\V1\LinkController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\PublicDashboardController;
use App\Http\Controllers\Api\V1\QrController;
use App\Http\Controllers\Api\V1\SharedDashboardController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TrackController;
use App\Http\Controllers\Api\V1\UtmTemplateController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Session auth (Sanctum SPA).
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    // Public.
    Route::get('qr', [QrController::class, 'show'])->middleware('throttle:60,1');

    Route::prefix('public/dashboards/{token}')->middleware('throttle:60,1')->group(function () {
        Route::get('/', [PublicDashboardController::class, 'show']);
        Route::get('analytics', [PublicDashboardController::class, 'analytics']);
    });

    // Authenticated, workspace-agnostic.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('workspaces', [WorkspaceController::class, 'index']);
        Route::post('workspaces', [WorkspaceController::class, 'store']);
        Route::post('invites/accept', [MemberController::class, 'accept']);
    });

    // Workspace-scoped: session (X-Workspace) or API key (Bearer shrt_…).
    Route::middleware(['workspace', 'throttle:api'])->group(function () {
        Route::get('workspace', [WorkspaceController::class, 'show']);
        Route::patch('workspace', [WorkspaceController::class, 'update']);
        Route::delete('workspace', [WorkspaceController::class, 'destroy']);

        Route::get('members', [MemberController::class, 'index']);
        Route::delete('members/{user}', [MemberController::class, 'destroy']);
        Route::get('invites', [MemberController::class, 'invites']);
        Route::post('invites', [MemberController::class, 'invite']);
        Route::delete('invites/{invite}', [MemberController::class, 'revokeInvite']);

        Route::get('api-keys', [ApiKeyController::class, 'index']);
        Route::post('api-keys', [ApiKeyController::class, 'store']);
        Route::delete('api-keys/{id}', [ApiKeyController::class, 'destroy']);

        // Links. Static segments are declared before `{link}` so they are never captured as an id.
        Route::get('links', [LinkController::class, 'index']);
        Route::post('links', [LinkController::class, 'store']);
        Route::get('links/count', [LinkController::class, 'count']);
        Route::get('links/info', [LinkController::class, 'info']);
        Route::put('links/upsert', [LinkController::class, 'upsert']);
        Route::post('links/bulk', [BulkLinkController::class, 'store']);
        Route::patch('links/bulk', [BulkLinkController::class, 'update']);
        Route::delete('links/bulk', [BulkLinkController::class, 'destroy']);
        Route::get('links/{link}', [LinkController::class, 'show']);
        Route::patch('links/{link}', [LinkController::class, 'update']);
        Route::delete('links/{link}', [LinkController::class, 'destroy']);

        Route::get('links/{link}/share', [SharedDashboardController::class, 'show']);
        Route::put('links/{link}/share', [SharedDashboardController::class, 'store']);
        Route::delete('links/{link}/share', [SharedDashboardController::class, 'destroy']);

        // Conversions & analytics.
        Route::post('track/lead', [TrackController::class, 'lead']);
        Route::post('track/sale', [TrackController::class, 'sale']);
        Route::get('analytics', [AnalyticsController::class, 'index']);
        Route::get('analytics/export', [AnalyticsController::class, 'export']);
        Route::get('events', [AnalyticsController::class, 'events']);
        Route::get('customers', [CustomerController::class, 'index']);
        Route::get('customers/{customer}', [CustomerController::class, 'show']);

        Route::apiResource('tags', TagController::class)->except('show');
        Route::apiResource('folders', FolderController::class)->except('show');
        Route::apiResource('utm-templates', UtmTemplateController::class)->except('show')->parameters(['utm-templates' => 'template']);

        Route::apiResource('webhooks', WebhookController::class)->except('show');
        Route::get('webhooks/{webhook}/deliveries', [WebhookController::class, 'deliveries']);
        Route::post('webhooks/{webhook}/test', [WebhookController::class, 'test']);

        Route::get('domains', [DomainController::class, 'index']);
        Route::post('domains', [DomainController::class, 'store']);
        Route::patch('domains/{domain}', [DomainController::class, 'update']);
        Route::post('domains/{domain}/verify', [DomainController::class, 'verify']);
        Route::delete('domains/{domain}', [DomainController::class, 'destroy']);
    });
});
