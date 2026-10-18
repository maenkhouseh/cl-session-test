<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WorkspaceController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return WorkspaceResource::collection($request->user()->workspaces()->orderBy('name')->get());
    }

    public function store(StoreWorkspaceRequest $request, CreateWorkspace $create): JsonResponse
    {
        $workspace = $create($request->user(), $request->string('name'), $request->input('slug'));

        return (new WorkspaceResource($workspace))->response()->setStatusCode(201);
    }

    public function show(): WorkspaceResource
    {
        return new WorkspaceResource($this->current->workspace());
    }

    public function update(UpdateWorkspaceRequest $request, UpdateWorkspace $update): WorkspaceResource
    {
        Gate::authorize('manage', $this->current->workspace());

        return new WorkspaceResource($update($this->current->workspace(), $request->validated()));
    }

    public function destroy(): JsonResponse
    {
        Gate::authorize('manage', $this->current->workspace());
        $this->current->workspace()->delete();

        return response()->json(null, 204);
    }
}
