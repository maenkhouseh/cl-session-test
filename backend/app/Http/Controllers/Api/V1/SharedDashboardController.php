<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Models\SharedDashboard;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Owner-side management of a link's public analytics dashboard. */
class SharedDashboardController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function show(Link $link): JsonResponse
    {
        $dashboard = SharedDashboard::query()->where('link_id', $link->id)->first();

        return response()->json(['data' => $dashboard ? $this->present($dashboard) : null]);
    }

    public function store(Request $request, Link $link): JsonResponse
    {
        $v = $request->validate([
            'password' => ['sometimes', 'nullable', 'string', 'min:4', 'max:100'],
            'doIndex' => ['sometimes', 'boolean'],
        ]);

        $dashboard = SharedDashboard::query()->firstOrNew(['link_id' => $link->id], ['workspace_id' => $this->current->workspace()->id]);
        if (array_key_exists('password', $v)) {
            $dashboard->password = $v['password'] ? Hash::make($v['password']) : null;
        }
        if (array_key_exists('doIndex', $v)) {
            $dashboard->do_index = $v['doIndex'];
        }
        $dashboard->save();

        return response()->json(['data' => $this->present($dashboard)], $dashboard->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Link $link): JsonResponse
    {
        SharedDashboard::query()->where('link_id', $link->id)->delete();

        return response()->json(null, 204);
    }

    private function present(SharedDashboard $d): array
    {
        return ['id' => $d->token, 'linkId' => $d->link_id, 'passwordProtected' => $d->password !== null, 'doIndex' => $d->do_index];
    }
}
