"use client";

import * as React from "react";
import { publicApi } from "@/lib/api";
import type { AnalyticsParams, GroupBy } from "@/types/api";
import { useWorkspaceApi } from "./use-workspace-api";

type Query = AnalyticsParams & { groupBy?: GroupBy };

/**
 * Where analytics data comes from. The dashboard reads the authenticated workspace API; the public share
 * page reads a password-gated, single-link endpoint. Both feed the same chart/list components.
 */
export interface AnalyticsSource {
  /** Cache-key prefix, unique per data source. */
  key: readonly unknown[];
  query<T>(params: Query, signal?: AbortSignal): Promise<T>;
}

const AnalyticsSourceContext = React.createContext<AnalyticsSource | null>(null);

export function useAnalyticsSource(): AnalyticsSource {
  const source = React.useContext(AnalyticsSourceContext);
  if (!source) throw new Error("useAnalyticsSource must be used inside an analytics source provider");
  return source;
}

export function WorkspaceAnalyticsSource({ children }: { children: React.ReactNode }) {
  const { slug, api } = useWorkspaceApi();
  const source = React.useMemo<AnalyticsSource>(() => ({ key: ["ws", slug, "analytics"], query: (params, signal) => api.analytics.query(params, signal) as never }), [slug, api]);
  return <AnalyticsSourceContext.Provider value={source}>{children}</AnalyticsSourceContext.Provider>;
}

export function PublicAnalyticsSource({ token, password, children }: { token: string; password?: string; children: React.ReactNode }) {
  const source = React.useMemo<AnalyticsSource>(() => ({ key: ["public", token, "analytics"], query: (params) => publicApi.analytics(token, params, password) as never }), [token, password]);
  return <AnalyticsSourceContext.Provider value={source}>{children}</AnalyticsSourceContext.Provider>;
}
