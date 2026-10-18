<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\RegisterUser;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser(
            $request->string('name'),
            $request->string('email'),
            $request->string('password'),
            $request->input('workspace_name'),
        );

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): UserResource
    {
        if (! Auth::guard('web')->attempt($request->only('email', 'password'))) {
            throw new ApiException('unauthorized', 'Invalid email or password.', 401);
        }

        $request->session()->regenerate();

        return new UserResource($request->user('web'));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
