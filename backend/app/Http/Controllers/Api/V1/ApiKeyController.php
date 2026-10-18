<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ApiKeys\CreateApiKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiKeyRequest;
use App\Http\Resources\ApiKeyResource;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ApiKeyController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function index(): AnonymousResourceCollection
    {
        return ApiKeyResource::collection($this->current->workspace()->apiKeys()->latest()->get());
    }

    public function store(StoreApiKeyRequest $request, CreateApiKey $create): JsonResponse
    {
        [$key, $plain] = $create(
            $this->current->workspace(),
            $this->current->user,
            $request->string('name'),
            null,
            $request->date('expires_at'),
        );

        return (new ApiKeyResource($key->setAttribute('plain_key', $plain)))->response()->setStatusCode(201);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->current->workspace()->apiKeys()->whereKey($id)->firstOrFail()->delete();

        return response()->json(null, 204);
    }
}
