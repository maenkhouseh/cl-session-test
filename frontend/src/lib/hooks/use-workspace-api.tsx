"use client";

import * as React from "react";
import { createApi, type WorkspaceApi } from "@/lib/api";

interface WorkspaceContextValue {
  slug: string;
  api: WorkspaceApi;
}

const WorkspaceContext = React.createContext<WorkspaceContextValue | null>(null);

export function WorkspaceProvider({ slug, children }: { slug: string; children: React.ReactNode }) {
  const value = React.useMemo(() => ({ slug, api: createApi(slug) }), [slug]);
  return <WorkspaceContext.Provider value={value}>{children}</WorkspaceContext.Provider>;
}

export function useWorkspaceApi(): WorkspaceContextValue {
  const ctx = React.useContext(WorkspaceContext);
  if (!ctx) throw new Error("useWorkspaceApi must be used inside <WorkspaceProvider>");
  return ctx;
}
