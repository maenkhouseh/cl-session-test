<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tags\SaveTag;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection($this->current->workspace()->tags()->orderBy('name')->get());
    }

    public function store(SaveTagRequest $request, SaveTag $save): JsonResponse
    {
        return (new TagResource($save($this->current->workspace(), $request->validated())))->response()->setStatusCode(201);
    }

    public function update(SaveTagRequest $request, Tag $tag, SaveTag $save): TagResource
    {
        return new TagResource($save($this->current->workspace(), $request->validated(), $tag));
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();

        return response()->json(['id' => $tag->id]);
    }
}
