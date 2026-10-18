<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\AnalyticsFilters;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyticsRequest;
use App\Models\SharedDashboard;
use App\Services\Analytics\AnalyticsQuery;
use App\Services\Analytics\EventsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Unauthenticated, read-only analytics for one shared link. Password via `X-Dashboard-Password`. */
class PublicDashboardController extends Controller
{
    public function __construct(private AnalyticsQuery $analytics, private EventsQuery $events) {}

    public function show(Request $request, string $token): JsonResponse
    {
        $dashboard = $this->find($token);
        $link = $dashboard->link;

        $locked = $dashboard->password !== null && ! $this->unlocked($request, $dashboard);

        return response()->json(['data' => [
            'id' => $dashboard->token,
            'passwordProtected' => $dashboard->password !== null,
            'locked' => $locked,
            'doIndex' => $dashboard->do_index,
            // Link details are withheld until the password is supplied.
            'link' => $locked ? null : ['domain' => $link->domain, 'key' => $link->key, 'shortLink' => $link->short_link, 'url' => $link->url, 'title' => $link->title],
        ]]);
    }

    public function analytics(AnalyticsRequest $request, string $token): JsonResponse
    {
        $dashboard = $this->authorised($request, $token);
        $workspace = $dashboard->link->workspace;
        $filters = AnalyticsFilters::fromInput($workspace, $request->validated(), $dashboard->link_id);

        return match ($groupBy = $request->validated('groupBy', 'count')) {
            'count' => response()->json($this->analytics->totals($workspace, $filters)),
            'timeseries' => response()->json($this->analytics->timeseries($workspace, $filters)),
            default => response()->json($this->analytics->top($workspace, $filters, $groupBy)),
        };
    }

    private function authorised(Request $request, string $token): SharedDashboard
    {
        $dashboard = $this->find($token);

        if ($dashboard->password !== null && ! $this->unlocked($request, $dashboard)) {
            throw new ApiException('unauthorized', 'This dashboard is password protected.', 401);
        }

        return $dashboard;
    }

    private function unlocked(Request $request, SharedDashboard $dashboard): bool
    {
        $attempt = (string) $request->header('X-Dashboard-Password', '');

        return $attempt !== '' && Hash::check($attempt, $dashboard->password);
    }

    private function find(string $token): SharedDashboard
    {
        $id = str_starts_with($token, 'dash_') ? substr($token, 5) : '';

        return SharedDashboard::query()->with('link.workspace')->find($id) ?? throw ApiException::notFound('Dashboard not found.');
    }
}
