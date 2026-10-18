<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Links\CreateLink;
use App\Actions\Links\DeleteLink;
use App\Actions\Links\UpdateLink;
use App\Actions\Links\UpsertLink;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LinkFilterRequest;
use App\Http\Requests\StoreLinkRequest;
use App\Http\Requests\UpdateLinkRequest;
use App\Http\Resources\LinkResource;
use App\Models\Link;
use App\Services\LinkQuery;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LinkController extends Controller
{
    public function __construct(private CurrentWorkspace $current, private LinkQuery $query) {}

    public function index(LinkFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $pageSize = (int) ($filters['pageSize'] ?? 50);
        $page = (int) ($filters['page'] ?? 1);

        // Fetch one extra row instead of running COUNT(*): the dashboard only needs "has more".
        $links = $this->query->sorted($this->query->filtered($this->current->workspace(), $filters), $filters)
            ->with('tags')->offset(($page - 1) * $pageSize)->limit($pageSize + 1)->get();

        return response()->json([
            'data' => LinkResource::collection($links->take($pageSize))->resolve(),
            'meta' => ['page' => $page, 'pageSize' => $pageSize, 'hasMore' => $links->count() > $pageSize],
        ]);
    }

    public function count(LinkFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = $this->query->filtered($this->current->workspace(), $filters)->reorder();

        return match ($filters['groupBy'] ?? null) {
            null => response()->json(['count' => $query->count()]),
            'tagId' => response()->json($this->countByTag($query)),
            default => response()->json($query->select(DB::raw($this->groupColumn($filters['groupBy']).' as "group"'), DB::raw('count(*) as count'))
                ->groupBy('group')->orderByDesc('count')->get()),
        };
    }

    public function store(StoreLinkRequest $request, CreateLink $create): JsonResponse
    {
        $link = $create($this->current->workspace(), $this->current->user, $request->validated());

        return (new LinkResource($link))->response()->setStatusCode(201);
    }

    public function show(Link $link): LinkResource
    {
        return new LinkResource($link->load('tags'));
    }

    /** Lookup by `?domain=&key=` or `?externalId=` (Dub's "retrieve a link"). */
    public function info(Request $request): LinkResource
    {
        $workspace = $this->current->workspace();

        $link = match (true) {
            $request->filled('externalId') => $workspace->links()->where('external_id', $request->query('externalId'))->first(),
            $request->filled('domain') && $request->filled('key') => $workspace->links()->forDomainKey($request->query('domain'), $request->query('key'))->first(),
            default => throw ApiException::badRequest('Provide either externalId, or both domain and key.'),
        };

        return new LinkResource(($link ?? throw ApiException::notFound('Link not found.'))->load('tags'));
    }

    public function update(UpdateLinkRequest $request, Link $link, UpdateLink $update): LinkResource
    {
        return new LinkResource($update($link, $request->validated()));
    }

    public function upsert(StoreLinkRequest $request, UpsertLink $upsert): JsonResponse
    {
        [$link, $created] = $upsert($this->current->workspace(), $this->current->user, $request->validated());

        return (new LinkResource($link))->response()->setStatusCode($created ? 201 : 200);
    }

    public function destroy(Link $link, DeleteLink $delete): JsonResponse
    {
        $delete($link);

        return response()->json(['id' => $link->id]);
    }

    private function groupColumn(string $groupBy): string
    {
        return ['domain' => 'domain', 'folderId' => 'folder_id', 'userId' => 'user_id'][$groupBy];
    }

    private function countByTag($query): array
    {
        return $query->join('link_tag', 'link_tag.link_id', '=', 'links.id')
            ->select('link_tag.tag_id as group', DB::raw('count(*) as count'))
            ->groupBy('link_tag.tag_id')->orderByDesc('count')->get()->all();
    }
}
