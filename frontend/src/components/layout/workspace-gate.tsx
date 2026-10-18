"use client";

import { useRouter } from "next/navigation";
import * as React from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api/client";
import { WorkspaceAnalyticsSource } from "@/lib/hooks/analytics-source";
import { WorkspaceProvider } from "@/lib/hooks/use-workspace-api";
import { useMe, useWorkspaces } from "@/lib/hooks/use-session";
import { DashboardShell } from "./dashboard-shell";

/** Auth + membership guard around the dashboard: 401 → /login, unknown workspace → first available one. */
export function WorkspaceGate({ slug, children }: { slug: string; children: React.ReactNode }) {
  const router = useRouter();
  const me = useMe();
  const workspaces = useWorkspaces(me.isSuccess);

  const unauthenticated = me.error instanceof ApiError && me.error.status === 401;
  const known = workspaces.data?.some((w) => w.slug === slug || w.id === slug);

  React.useEffect(() => {
    if (unauthenticated) {
      router.replace(`/login?next=${encodeURIComponent(window.location.pathname)}`);
    } else if (workspaces.data && !known) {
      router.replace(workspaces.data.length ? `/app/${workspaces.data[0].slug}/links` : "/app/new");
    }
  }, [unauthenticated, workspaces.data, known, router]);

  if (!me.isSuccess || !workspaces.data || !known) {
    return (
      <div className="mx-auto max-w-6xl space-y-4 p-8" aria-busy="true" aria-label="Loading">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  return (
    <WorkspaceProvider slug={slug}>
      <WorkspaceAnalyticsSource>
        <DashboardShell slug={slug}>{children}</DashboardShell>
      </WorkspaceAnalyticsSource>
    </WorkspaceProvider>
  );
}
