"use client";

import { keepPreviousData, useInfiniteQuery, useQuery } from "@tanstack/react-query";
import type { AnalyticsParams, GroupBy, SeriesPoint, TopRow, Totals } from "@/types/api";
import { useAnalyticsSource } from "./analytics-source";
import { useWorkspaceApi } from "./use-workspace-api";

type Params = AnalyticsParams;

export function useTotals(params: Params) {
  const source = useAnalyticsSource();
  return useQuery({
    queryKey: [...source.key, "count", params],
    queryFn: ({ signal }) => source.query<Totals>({ ...params, groupBy: "count" }, signal),
    placeholderData: keepPreviousData,
  });
}

export function useTimeseries(params: Params) {
  const source = useAnalyticsSource();
  return useQuery({
    queryKey: [...source.key, "timeseries", params],
    queryFn: ({ signal }) => source.query<SeriesPoint[]>({ ...params, groupBy: "timeseries" }, signal),
    placeholderData: keepPreviousData,
  });
}

export function useTop(groupBy: GroupBy, params: Params, enabled = true) {
  const source = useAnalyticsSource();
  return useQuery({
    queryKey: [...source.key, groupBy, params],
    queryFn: ({ signal }) => source.query<TopRow[]>({ ...params, groupBy }, signal),
    placeholderData: keepPreviousData,
    enabled,
  });
}

/** Keyset-paginated event stream ("Load more" appends the next page). */
export function useEvents(params: Params) {
  const { slug, api } = useWorkspaceApi();
  return useInfiniteQuery({
    queryKey: ["ws", slug, "events", params],
    queryFn: ({ pageParam, signal }) => api.analytics.events({ ...params, cursor: pageParam || undefined, limit: 50 }, signal),
    initialPageParam: "",
    getNextPageParam: (last) => last.meta.nextCursor ?? undefined,
  });
}
