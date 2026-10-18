<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UtmTemplates\SaveUtmTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUtmTemplateRequest;
use App\Http\Resources\UtmTemplateResource;
use App\Models\UtmTemplate;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UtmTemplateController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function index(): AnonymousResourceCollection
    {
        return UtmTemplateResource::collection($this->current->workspace()->utmTemplates()->orderBy('name')->get());
    }

    public function store(SaveUtmTemplateRequest $request, SaveUtmTemplate $save): JsonResponse
    {
        return (new UtmTemplateResource($save($this->current->workspace(), $request->validated())))->response()->setStatusCode(201);
    }

    public function update(SaveUtmTemplateRequest $request, UtmTemplate $template, SaveUtmTemplate $save): UtmTemplateResource
    {
        return new UtmTemplateResource($save($this->current->workspace(), $request->validated(), $template));
    }

    public function destroy(UtmTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json(['id' => $template->id]);
    }
}
