"use client";

import { useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import * as React from "react";
import { Button } from "@/components/ui/button";
import { workspacesApi } from "@/lib/api";
import { ApiError } from "@/lib/api/client";
import { useApiMutation } from "@/lib/hooks/use-api-mutation";
import { sessionKeys, useMe } from "@/lib/hooks/use-session";
import { AuthLayout } from "./auth-layout";

export function AcceptInvite({ token }: { token: string }) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const me = useMe();
  const [error, setError] = React.useState<string | null>(null);
  const accept = useApiMutation({
    mutationFn: () => workspacesApi.acceptInvite(token),
    silent: true,
    onSuccess: async (workspace) => {
      await queryClient.invalidateQueries({ queryKey: sessionKeys.workspaces });
      router.replace(`/app/${workspace.slug}/links`);
    },
  });

  const unauthenticated = me.error instanceof ApiError && me.error.status === 401;
  React.useEffect(() => {
    if (unauthenticated) router.replace(`/login?next=${encodeURIComponent(`/invite/${token}`)}`);
  }, [unauthenticated, router, token]);

  return (
    <AuthLayout title="You've been invited" description="Join the workspace to start collaborating on links and analytics." footer={<></>}>
      {error && <p role="alert" className="rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive">{error}</p>}
      <Button className="w-full" loading={accept.isPending || me.isPending} disabled={!me.isSuccess} onClick={() => { setError(null); accept.mutate(undefined, { onError: (e) => setError(e instanceof ApiError ? e.message : "Could not accept the invitation.") }); }}>
        Accept invitation
      </Button>
    </AuthLayout>
  );
}
