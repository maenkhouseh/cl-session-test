"use client";

import { Pencil, Send, Trash2, Webhook as WebhookIcon } from "lucide-react";
import * as React from "react";
import { ConfirmDialog } from "@/components/forms/confirm-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api/client";
import { planAllows } from "@/lib/constants/plans";
import { useWorkspaceInfo } from "@/lib/hooks/use-library";
import { useWebhookDeliveries, useWebhookMutations, useWebhooks } from "@/lib/hooks/use-team";
import { formatDateTime, timeAgo } from "@/lib/utils/format";
import type { Webhook, WebhookTrigger } from "@/types/api";
import { SecretDialog } from "./secret-dialog";
import { SettingsCard } from "./settings-card";

const TRIGGERS: { value: WebhookTrigger; label: string }[] = [
  { value: "link.created", label: "Link created" }, { value: "link.updated", label: "Link updated" }, { value: "link.deleted", label: "Link deleted" },
  { value: "link.clicked", label: "Link clicked" }, { value: "lead.created", label: "Lead created" }, { value: "sale.created", label: "Sale created" },
];

export function WebhooksSettings() {
  const info = useWorkspaceInfo();
  const hooks = useWebhooks();
  const { create, update, remove, test } = useWebhookMutations();
  const [editing, setEditing] = React.useState<Webhook | "new" | null>(null);
  const [deleting, setDeleting] = React.useState<Webhook | null>(null);
  const [viewing, setViewing] = React.useState<Webhook | null>(null);
  const [secret, setSecret] = React.useState<string | null>(null);
  const allowed = planAllows(info.data?.plan, "webhooks");

  return (
    <>
      <SettingsCard title="Webhooks" description="Get real-time HTTP callbacks for link, lead and sale events." actions={<Button size="sm" disabled={!allowed} onClick={() => setEditing("new")}>Add webhook</Button>}>
        {info.data && !allowed && <p className="mb-3 rounded-lg bg-warning/10 px-3 py-2 text-sm text-warning">Webhooks are available on paid plans.</p>}
        {hooks.isPending ? <Skeleton className="h-24 w-full" /> : hooks.data?.length === 0 ? (
          <EmptyState icon={<WebhookIcon />} title="No webhooks" description="Payloads are signed with an HMAC so you can verify they came from Shortie." className="py-8" />
        ) : (
          <ul className="divide-y divide-border">
            {hooks.data?.map((h) => (
              <li key={h.id} className="flex flex-wrap items-center gap-3 py-3">
                <div className="min-w-0 flex-1">
                  <p className="flex items-center gap-2 text-sm font-medium">{h.name} {h.disabled ? <Badge variant="danger">Disabled</Badge> : h.consecutiveFailures > 0 ? <Badge variant="warning">{h.consecutiveFailures} failures</Badge> : <Badge variant="success">Active</Badge>}</p>
                  <p className="truncate font-mono text-xs text-muted-foreground">{h.url}</p>
                  <p className="mt-1 text-xs text-muted-foreground">{h.triggers.join(", ")}</p>
                </div>
                <Button variant="secondary" size="sm" onClick={() => test.mutate(h.id)} loading={test.isPending && test.variables === h.id}><Send /> Test</Button>
                <Button variant="ghost" size="sm" onClick={() => setViewing(h)}>Deliveries</Button>
                <Button variant="ghost" size="icon" aria-label={`Edit ${h.name}`} onClick={() => setEditing(h)}><Pencil /></Button>
                <Button variant="ghost" size="icon" aria-label={`Delete ${h.name}`} onClick={() => setDeleting(h)}><Trash2 /></Button>
              </li>
            ))}
          </ul>
        )}
      </SettingsCard>

      <WebhookDialog
        key={editing === null ? "closed" : editing === "new" ? "new" : editing.id}
        webhook={editing === "new" ? undefined : editing ?? undefined}
        open={editing !== null}
        onOpenChange={(o) => !o && setEditing(null)}
        pending={create.isPending || update.isPending}
        onSubmit={(values, fail) => {
          if (editing && editing !== "new") update.mutate({ id: editing.id, ...values }, { onSuccess: () => setEditing(null), onError: fail });
          else create.mutate(values, { onSuccess: (w) => { setEditing(null); setSecret(w.secret ?? null); }, onError: fail });
        }}
      />
      <DeliveriesDialog webhook={viewing} onClose={() => setViewing(null)} />
      <SecretDialog open={secret !== null} onOpenChange={(o) => !o && setSecret(null)} title="Webhook signing secret" secret={secret} hint="Use this to verify the Shortie-Signature header. It won't be shown again." />
      <ConfirmDialog open={!!deleting} onOpenChange={(o) => !o && setDeleting(null)} title={`Delete “${deleting?.name}”?`} description="Events will no longer be sent to this endpoint." confirmLabel="Delete" destructive loading={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })} />
    </>
  );
}

function WebhookDialog({ webhook, open, onOpenChange, pending, onSubmit }: { webhook?: Webhook; open: boolean; onOpenChange: (o: boolean) => void; pending: boolean; onSubmit: (v: { name: string; url: string; triggers: string[] }, fail: (e: unknown) => void) => void }) {
  const [name, setName] = React.useState(webhook?.name ?? "");
  const [url, setUrl] = React.useState(webhook?.url ?? "");
  const [triggers, setTriggers] = React.useState<string[]>(webhook?.triggers ?? ["link.created"]);
  const [error, setError] = React.useState<string | null>(null);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent title={webhook ? "Edit webhook" : "Add webhook"} className="max-w-md">
        <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); setError(null); onSubmit({ name, url, triggers }, (err) => setError(err instanceof ApiError ? err.message : "Could not save the webhook.")); }}>
          <Field label="Name" htmlFor="hook-name"><Input id="hook-name" required value={name} onChange={(e) => setName(e.target.value)} /></Field>
          <Field label="Endpoint URL" htmlFor="hook-url" hint="Must be a public https URL" error={error ?? undefined}><Input id="hook-url" required type="url" placeholder="https://example.com/webhooks/shortie" value={url} onChange={(e) => setUrl(e.target.value)} /></Field>
          <fieldset className="space-y-2"><legend className="text-sm font-medium">Events</legend>
            <div className="grid grid-cols-2 gap-2">
              {TRIGGERS.map((t) => (
                <label key={t.value} className="flex items-center gap-2 text-sm"><input type="checkbox" className="size-4 accent-[var(--accent)]" checked={triggers.includes(t.value)} onChange={(e) => setTriggers((c) => (e.target.checked ? [...c, t.value] : c.filter((x) => x !== t.value)))} />{t.label}</label>
              ))}
            </div>
          </fieldset>
          <div className="flex justify-end"><Button type="submit" loading={pending} disabled={triggers.length === 0}>{webhook ? "Save" : "Create webhook"}</Button></div>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function DeliveriesDialog({ webhook, onClose }: { webhook: Webhook | null; onClose: () => void }) {
  const deliveries = useWebhookDeliveries(webhook?.id ?? null);
  return (
    <Dialog open={!!webhook} onOpenChange={(o) => !o && onClose()}>
      <DialogContent title="Recent deliveries" description={webhook?.url} className="max-w-xl">
        {deliveries.isPending ? <Skeleton className="h-24 w-full" /> : deliveries.data?.length === 0 ? <p className="text-sm text-muted-foreground">Nothing sent yet.</p> : (
          <ul className="divide-y divide-border">
            {deliveries.data?.map((d) => (
              <li key={d.id} className="flex items-center gap-3 py-2 text-sm">
                <Badge variant={d.status === "success" ? "success" : d.status === "failed" ? "danger" : "warning"}>{d.status}</Badge>
                <span className="font-mono text-xs">{d.event}</span>
                <span className="text-muted-foreground">{d.response_code ?? "—"}</span>
                <span className="ml-auto text-xs text-muted-foreground" title={formatDateTime(d.created_at)}>{timeAgo(d.created_at)}</span>
              </li>
            ))}
          </ul>
        )}
      </DialogContent>
    </Dialog>
  );
}
