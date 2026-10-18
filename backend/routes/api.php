<?php

use App\Http\Controllers\Api\V1\ApiKeyController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MemberController;
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
    });
});
