"use client";

import { useRouter } from "next/navigation";
import * as React from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api/client";
import { useMe, useWorkspaces } from "@/lib/hooks/use-session";

/** `/app` → the user's first workspace, or workspace creation when they have none. */
export function AppEntry() {
  const router = useRouter();
  const me = useMe();
  const workspaces = useWorkspaces(me.isSuccess);

  React.useEffect(() => {
    if (me.error instanceof ApiError && me.error.status === 401) {
      router.replace("/login?next=/app");
    } else if (workspaces.data) {
      router.replace(workspaces.data.length ? `/app/${workspaces.data[0].slug}/links` : "/app/new");
    }
  }, [me.error, workspaces.data, router]);

  return (
    <div className="mx-auto max-w-md space-y-3 p-12" aria-busy="true" aria-label="Loading">
      <Skeleton className="h-6 w-40" />
      <Skeleton className="h-24 w-full" />
    </div>
  );
}
