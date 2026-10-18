<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Links\BulkCreateLinks;
use App\Actions\Links\BulkDeleteLinks;
use App\Actions\Links\BulkUpdateLinks;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkLinksRequest;
use App\Http\Resources\LinkResource;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BulkLinkController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function store(BulkLinksRequest $request, BulkCreateLinks $bulk): JsonResponse
    {
        $results = $bulk($this->current->workspace(), $this->current->user, $request->validated('links'));

        return response()->json(['data' => array_map(
            fn ($r) => isset($r['link']) ? (new LinkResource($r['link']))->resolve() : ['error' => $r['error']],
            $results,
        )], 201);
    }

    public function update(BulkLinksRequest $request, BulkUpdateLinks $bulk): JsonResponse
    {
        $links = $bulk($this->current->workspace(), $request->validated('linkIds'), $request->validated('data', []));

        return response()->json(['data' => LinkResource::collection($links)->resolve()]);
    }

    public function destroy(Request $request, BulkDeleteLinks $bulk): JsonResponse
    {
        $ids = array_slice(array_filter(explode(',', (string) $request->query('linkIds'))), 0, 100);
        abort_if($ids === [], 400, 'linkIds is required.');

        return response()->json(['deletedCount' => $bulk($this->current->workspace(), $ids)]);
    }
}
