<?php

namespace App\Data;

use App\Models\Workspace;
use Carbon\CarbonImmutable;
use DateTimeZone;

/**
 * Normalised, validated analytics scope. Built once per request and handed to the query services.
 * Dimension filters are `[column => ['values' => [...], 'negate' => bool]]`.
 */
final readonly class AnalyticsFilters
{
    /** API name => column for every event dimension that can be filtered or grouped. */
    public const DIMENSIONS = [
        'country' => 'country', 'region' => 'region', 'city' => 'city', 'continent' => 'continent',
        'device' => 'device', 'browser' => 'browser', 'os' => 'os', 'referer' => 'referer', 'refererUrl' => 'referer_url',
        'url' => 'url', 'utm_source' => 'utm_source', 'utm_medium' => 'utm_medium', 'utm_campaign' => 'utm_campaign',
        'utm_term' => 'utm_term', 'utm_content' => 'utm_content',
    ];

    /** @param array<string, array{values: array<int, string>, negate: bool}> $dimensions */
    public function __construct(
        public string $event,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $timezone,
        public ?string $linkId,
        public ?string $domain,
        public ?string $key,
        public array $tagIds,
        public ?string $folderId,
        public array $dimensions,
        public ?string $trigger,
        public bool $uniqueOnly,
    ) {}

    /** @param array<string, mixed> $input validated request data */
    public static function fromInput(Workspace $workspace, array $input, ?string $lockedLinkId = null): self
    {
        $timezone = $input['timezone'] ?? 'UTC';
        $end = isset($input['end']) ? CarbonImmutable::parse($input['end'])->utc() : CarbonImmutable::now('UTC');
        $start = isset($input['start'])
            ? CarbonImmutable::parse($input['start'])->utc()
            : self::startFor($input['interval'] ?? '24h', $end, $timezone);

        // Never read further back than the plan retains.
        if (($days = $workspace->plan->limit('retention_days')) !== null) {
            $start = $start->max($end->subDays($days));
        }

        $dimensions = [];
        foreach (self::DIMENSIONS as $param => $column) {
            if (filled($input[$param] ?? null)) {
                $dimensions[$column] = self::parseValues((string) $input[$param]);
            }
        }

        return new self(
            event: $input['event'] ?? 'clicks',
            start: $start,
            end: $end,
            timezone: $timezone,
            linkId: $lockedLinkId ?? ($input['linkId'] ?? null),
            domain: $lockedLinkId ? null : ($input['domain'] ?? null),
            key: $lockedLinkId ? null : ($input['key'] ?? null),
            tagIds: $lockedLinkId ? [] : array_values($input['tagIds'] ?? []),
            folderId: $lockedLinkId ? null : ($input['folderId'] ?? null),
            dimensions: $dimensions,
            trigger: $input['trigger'] ?? null,
            uniqueOnly: (bool) ($input['unique'] ?? false),
        );
    }

    /** Same scope over a different time window (used to split a range into roll-up + raw segments). */
    public function withWindow(CarbonImmutable $start, CarbonImmutable $end): self
    {
        return new self($this->event, $start, $end, $this->timezone, $this->linkId, $this->domain, $this->key, $this->tagIds,
            $this->folderId, $this->dimensions, $this->trigger, $this->uniqueOnly);
    }

    /** True when only link-level scoping applies, i.e. the pre-aggregated daily table can answer the query. */
    public function isLinkLevelOnly(): bool
    {
        return $this->dimensions === [] && $this->trigger === null && ! $this->uniqueOnly;
    }

    /** `US,CA` → in-list; `-US,-CA` → not-in-list. */
    private static function parseValues(string $raw): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($p) => $p !== ''));
        $negate = $parts !== [] && str_starts_with($parts[0], '-');

        return [
            'values' => array_map(fn ($p) => $negate ? ltrim($p, '-') : $p, $parts),
            'negate' => $negate,
        ];
    }

    private static function startFor(string $interval, CarbonImmutable $end, string $timezone): CarbonImmutable
    {
        $local = $end->setTimezone(new DateTimeZone($timezone));

        return match ($interval) {
            '1h' => $end->subHour(),
            '24h' => $end->subDay(),
            '7d' => $end->subDays(7),
            '30d' => $end->subDays(30),
            '90d' => $end->subDays(90),
            '1y' => $end->subYear(),
            'mtd' => $local->startOfMonth()->utc(),
            'qtd' => $local->startOfQuarter()->utc(),
            'ytd' => $local->startOfYear()->utc(),
            default => CarbonImmutable::create(2000, 1, 1, 0, 0, 0, 'UTC'), // 'all'
        };
    }

    /** hour | day | month — keeps charts at ≲ 100 points regardless of range. */
    public function granularity(): string
    {
        $hours = $this->start->diffInHours($this->end);

        return match (true) {
            $hours <= 48 => 'hour',
            $hours <= 92 * 24 => 'day',
            default => 'month',
        };
    }
}
