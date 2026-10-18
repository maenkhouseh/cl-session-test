"use client";

import { KeyRound, Trash2 } from "lucide-react";
import * as React from "react";
import { ConfirmDialog } from "@/components/forms/confirm-dialog";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api/client";
import { useApiKeyMutations, useApiKeys } from "@/lib/hooks/use-team";
import { timeAgo } from "@/lib/utils/format";
import type { ApiKey } from "@/types/api";
import { SecretDialog } from "./secret-dialog";
import { SettingsCard } from "./settings-card";

export function ApiKeysSettings() {
  const keys = useApiKeys();
  const { create, remove } = useApiKeyMutations();
  const [name, setName] = React.useState("");
  const [error, setError] = React.useState<string | null>(null);
  const [secret, setSecret] = React.useState<string | null>(null);
  const [revoking, setRevoking] = React.useState<ApiKey | null>(null);

  return (
    <>
      <SettingsCard title="Create an API key" description="Use keys to call the Shortie API from your servers. Authenticate with `Authorization: Bearer <key>`.">
        <form className="flex max-w-md gap-2" onSubmit={(e) => { e.preventDefault(); setError(null); create.mutate({ name }, { onSuccess: (k) => { setName(""); setSecret(k.key ?? null); }, onError: (err) => setError(err instanceof ApiError ? err.message : "Could not create the key.") }); }}>
          <Input aria-label="Key name" placeholder="Production server" required value={name} onChange={(e) => setName(e.target.value)} />
          <Button type="submit" loading={create.isPending}>Create key</Button>
        </form>
        {error && <p role="alert" className="mt-2 text-sm text-destructive">{error}</p>}
      </SettingsCard>

      <SettingsCard title="API keys">
        {keys.isPending ? <Skeleton className="h-20 w-full" /> : keys.data?.length === 0 ? (
          <EmptyState icon={<KeyRound />} title="No API keys" className="py-8" />
        ) : (
          <ul className="divide-y divide-border">
            {keys.data?.map((k) => (
              <li key={k.id} className="flex items-center gap-3 py-2.5">
                <div className="min-w-0 flex-1"><p className="text-sm font-medium">{k.name}</p><p className="font-mono text-xs text-muted-foreground">{k.preview}</p></div>
                <p className="hidden text-xs text-muted-foreground sm:block">{k.lastUsedAt ? `Used ${timeAgo(k.lastUsedAt)}` : "Never used"}</p>
                <Button variant="ghost" size="icon" aria-label={`Revoke ${k.name}`} onClick={() => setRevoking(k)}><Trash2 /></Button>
              </li>
            ))}
          </ul>
        )}
      </SettingsCard>

      <SecretDialog open={secret !== null} onOpenChange={(o) => !o && setSecret(null)} title="Your new API key" secret={secret} hint="Copy this key now — for your security it won't be shown again." />
      <ConfirmDialog open={!!revoking} onOpenChange={(o) => !o && setRevoking(null)} title={`Revoke “${revoking?.name}”?`} description="Anything using this key will immediately lose access." confirmLabel="Revoke" destructive loading={remove.isPending}
        onConfirm={() => revoking && remove.mutate(revoking.id, { onSuccess: () => setRevoking(null) })} />
    </>
  );
}
