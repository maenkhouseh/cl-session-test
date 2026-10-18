<?php

use App\Http\Controllers\Redirect\PasswordController;
use App\Http\Controllers\Redirect\RedirectController;
use Illuminate\Support\Facades\Route;

/*
 * Short-link runtime, served on every short domain. Registered with NO middleware group:
 * no session, no cookie encryption, no CSRF — the hot path must stay as lean as possible.
 * The key pattern excludes paths owned by the API (`api/…`) and health checks.
 */
$key = '(?!api(?:/|$)|up$)[A-Za-z0-9][A-Za-z0-9_\-/]*';

Route::get('/', [RedirectController::class, 'root']);
Route::get('/{key}', [RedirectController::class, 'show'])->where('key', $key);
Route::post('/{key}', [PasswordController::class, 'verify'])->where('key', $key);
