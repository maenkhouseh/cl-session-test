<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Folders\SaveFolder;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveFolderRequest;
use App\Http\Resources\FolderResource;
use App\Models\Folder;
use App\Support\CaseConverter;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FolderController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function index(): AnonymousResourceCollection
    {
        // withCount keeps the per-folder link totals to one grouped subquery rather than N counts.
        return FolderResource::collection($this->current->workspace()->folders()->withCount('links')->orderBy('name')->get());
    }

    public function store(SaveFolderRequest $request, SaveFolder $save): JsonResponse
    {
        $folder = $save($this->current->workspace(), CaseConverter::toSnake($request->validated()));

        return (new FolderResource($folder))->response()->setStatusCode(201);
    }

    public function update(SaveFolderRequest $request, Folder $folder, SaveFolder $save): FolderResource
    {
        return new FolderResource($save($this->current->workspace(), CaseConverter::toSnake($request->validated()), $folder));
    }

    public function destroy(Folder $folder): JsonResponse
    {
        $folder->delete(); // links.folder_id is ON DELETE SET NULL — links survive, unfiled.

        return response()->json(['id' => $folder->id]);
    }
}
