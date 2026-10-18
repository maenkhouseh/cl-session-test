"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import * as React from "react";
import type { AnalyticsEvent, AnalyticsParams, Interval } from "@/types/api";

/** Query-string keys that act as analytics filters (everything except interval/event). */
export const FILTER_KEYS = [
  "linkId", "folderId", "tagIds", "trigger", "domain", "key",
  "country", "region", "city", "continent", "device", "browser", "os", "referer", "refererUrl", "url",
  "utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content",
] as const;

export const INTERVALS: { value: Interval; label: string }[] = [
  { value: "24h", label: "Last 24 hours" },
  { value: "7d", label: "Last 7 days" },
  { value: "30d", label: "Last 30 days" },
  { value: "90d", label: "Last 3 months" },
  { value: "1y", label: "Last 12 months" },
  { value: "mtd", label: "Month to date" },
  { value: "qtd", label: "Quarter to date" },
  { value: "ytd", label: "Year to date" },
  { value: "all", label: "All time" },
];

const EVENTS: AnalyticsEvent[] = ["clicks", "leads", "sales"];

/**
 * Analytics state lives in the URL so any view can be bookmarked, shared or deep-linked
 * (e.g. the "Analytics" action on a link row opens `?linkId=…`).
 */
export function useAnalyticsParams(defaultInterval: Interval = "30d") {
  const search = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();

  const interval = (INTERVALS.some((i) => i.value === search.get("interval")) ? search.get("interval") : defaultInterval) as Interval;
  const event = (EVENTS.includes(search.get("event") as AnalyticsEvent) ? search.get("event") : "clicks") as AnalyticsEvent;

  const filters = React.useMemo(() => {
    const out: Record<string, string> = {};
    for (const key of FILTER_KEYS) {
      const value = search.get(key);
      if (value) out[key] = value;
    }
    return out;
  }, [search]);

  /** Params for totals/timeseries/lists (no `event`: totals always return all three). */
  const params = React.useMemo<AnalyticsParams>(() => ({ interval, ...filters, tagIds: filters.tagIds?.split(",") }), [interval, filters]);

  const update = React.useCallback(
    (patch: Record<string, string | undefined>) => {
      const next = new URLSearchParams(search.toString());
      for (const [key, value] of Object.entries(patch)) {
        if (value === undefined || value === "") next.delete(key);
        else next.set(key, value);
      }
      const qs = next.toString();
      router.replace(qs ? `${pathname}?${qs}` : pathname, { scroll: false });
    },
    [search, router, pathname],
  );

  const clearFilters = React.useCallback(() => update(Object.fromEntries(FILTER_KEYS.map((k) => [k, undefined]))), [update]);

  return { interval, event, filters, params, update, clearFilters };
}
