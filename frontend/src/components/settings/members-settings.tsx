"use client";

import { Mail, Trash2 } from "lucide-react";
import * as React from "react";
import { ConfirmDialog } from "@/components/forms/confirm-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api/client";
import { useWorkspaces, useMe } from "@/lib/hooks/use-session";
import { useInvites, useMembers, useTeamMutations } from "@/lib/hooks/use-team";
import { useWorkspaceApi } from "@/lib/hooks/use-workspace-api";
import { formatDate, timeAgo } from "@/lib/utils/format";
import type { Member } from "@/types/api";
import { SettingsCard } from "./settings-card";

export function MembersSettings() {
  const { slug } = useWorkspaceApi();
  const me = useMe();
  const members = useMembers();
  const workspaces = useWorkspaces();
  const isOwner = workspaces.data?.find((w) => w.slug === slug)?.role === "owner";
  const invites = useInvites(!!isOwner);
  const { invite, revokeInvite, removeMember } = useTeamMutations();
  const [email, setEmail] = React.useState("");
  const [error, setError] = React.useState<string | null>(null);
  const [removing, setRemoving] = React.useState<Member | null>(null);

  return (
    <>
      {isOwner && (
        <SettingsCard title="Invite a teammate" description="They'll receive an email with a link to join this workspace.">
          <form className="flex max-w-md gap-2" onSubmit={(e) => { e.preventDefault(); setError(null); invite.mutate({ email }, { onSuccess: () => setEmail(""), onError: (err) => setError(err instanceof ApiError ? err.message : "Could not send the invitation.") }); }}>
            <Input type="email" required aria-label="Email address" placeholder="teammate@company.com" value={email} onChange={(e) => setEmail(e.target.value)} />
            <Button type="submit" loading={invite.isPending}><Mail /> Invite</Button>
          </form>
          {error && <p role="alert" className="mt-2 text-sm text-destructive">{error}</p>}
        </SettingsCard>
      )}

      <SettingsCard title="Members" description={`${members.data?.length ?? "…"} in this workspace`}>
        {members.isPending ? <Skeleton className="h-24 w-full" /> : (
          <ul className="divide-y divide-border">
            {members.data?.map((m) => (
              <li key={m.id} className="flex items-center gap-3 py-3">
                <span className="flex size-9 items-center justify-center rounded-full bg-muted text-sm font-semibold">{m.name.charAt(0).toUpperCase()}</span>
                <div className="min-w-0 flex-1"><p className="truncate text-sm font-medium">{m.name}{m.id === me.data?.id && " (you)"}</p><p className="truncate text-xs text-muted-foreground">{m.email} · joined {formatDate(m.joinedAt)}</p></div>
                <Badge variant={m.role === "owner" ? "accent" : "neutral"}>{m.role}</Badge>
                {m.role !== "owner" && (isOwner || m.id === me.data?.id) && (
                  <Button variant="ghost" size="icon" aria-label={m.id === me.data?.id ? "Leave workspace" : `Remove ${m.name}`} onClick={() => setRemoving(m)}><Trash2 /></Button>
                )}
              </li>
            ))}
          </ul>
        )}
      </SettingsCard>

      {isOwner && !!invites.data?.length && (
        <SettingsCard title="Pending invitations">
          <ul className="divide-y divide-border">
            {invites.data.map((i) => (
              <li key={i.id} className="flex items-center gap-3 py-3">
                <div className="min-w-0 flex-1"><p className="truncate text-sm font-medium">{i.email}</p><p className="text-xs text-muted-foreground">Expires {timeAgo(i.expiresAt)}</p></div>
                <Button variant="ghost" size="sm" loading={revokeInvite.isPending} onClick={() => revokeInvite.mutate(i.id)}>Revoke</Button>
              </li>
            ))}
          </ul>
        </SettingsCard>
      )}

      <ConfirmDialog
        open={!!removing}
        onOpenChange={(o) => !o && setRemoving(null)}
        title={removing?.id === me.data?.id ? "Leave workspace?" : `Remove ${removing?.name}?`}
        description="They will lose access to this workspace and their API keys will be revoked."
        confirmLabel={removing?.id === me.data?.id ? "Leave" : "Remove"}
        destructive
        loading={removeMember.isPending}
        onConfirm={() => removing && removeMember.mutate(removing.id, { onSuccess: () => setRemoving(null) })}
      />
    </>
  );
}
