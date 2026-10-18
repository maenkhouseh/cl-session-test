<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\AnalyticsFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyticsRequest;
use App\Services\Analytics\AnalyticsQuery;
use App\Services\Analytics\EventsQuery;
use App\Support\Csv;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    private const CSV_COLUMNS = [
        'timestamp', 'event', 'eventName', 'link', 'url', 'customer', 'country', 'region', 'city', 'continent',
        'device', 'browser', 'os', 'referer', 'refererUrl', 'qr', 'amount', 'currency', 'invoiceId',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    ];

    public function __construct(private CurrentWorkspace $current, private AnalyticsQuery $analytics, private EventsQuery $events) {}

    public function index(AnalyticsRequest $request): JsonResponse
    {
        $workspace = $this->current->workspace();
        $filters = AnalyticsFilters::fromInput($workspace, $request->validated());

        return match ($groupBy = $request->validated('groupBy', 'count')) {
            'count' => response()->json($this->analytics->totals($workspace, $filters)),
            'timeseries' => response()->json($this->analytics->timeseries($workspace, $filters)),
            default => response()->json($this->analytics->top($workspace, $filters, $groupBy)),
        };
    }

    public function events(AnalyticsRequest $request): JsonResponse
    {
        $filters = AnalyticsFilters::fromInput($this->current->workspace(), $request->validated());
        $page = $this->events->page($this->current->workspace(), $filters, $request->validated('cursor'), (int) $request->validated('limit', 50));

        return response()->json(['data' => $page['rows'], 'meta' => ['nextCursor' => $page['nextCursor']]]);
    }

    public function export(AnalyticsRequest $request): StreamedResponse
    {
        $workspace = $this->current->workspace();
        $filters = AnalyticsFilters::fromInput($workspace, $request->validated());
        $name = "{$filters->event}-".now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($workspace, $filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::CSV_COLUMNS);

            foreach ($this->events->stream($workspace, $filters) as $row) {
                $row['link'] = $row['link']['shortLink'] ?? '';
                $row['customer'] = $row['customer']['email'] ?? $row['customer']['externalId'] ?? '';
                fputcsv($out, array_map(fn ($col) => Csv::cell($row[$col] ?? null), self::CSV_COLUMNS));
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
