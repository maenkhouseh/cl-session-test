"use client";

import { CheckCircle2, Clock, Globe, Star, Trash2 } from "lucide-react";
import * as React from "react";
import { CopyButton } from "@/components/forms/copy-button";
import { ConfirmDialog } from "@/components/forms/confirm-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api/client";
import { useDomainMutations, useDomains } from "@/lib/hooks/use-library";
import type { Domain } from "@/types/api";
import { SettingsCard } from "./settings-card";

export function DomainsSettings() {
  const domains = useDomains();
  const { create, update, verify, remove } = useDomainMutations();
  const [slug, setSlug] = React.useState("");
  const [error, setError] = React.useState<string | null>(null);
  const [deleting, setDeleting] = React.useState<Domain | null>(null);

  return (
    <>
      <SettingsCard title="Add a custom domain" description="Brand your short links, e.g. go.acme.com. You'll point DNS at us in the next step.">
        <form className="flex max-w-md gap-2" onSubmit={(e) => { e.preventDefault(); setError(null); create.mutate(slug, { onSuccess: () => setSlug(""), onError: (err) => setError(err instanceof ApiError ? err.message : "Could not add the domain.") }); }}>
          <Input aria-label="Domain" placeholder="go.acme.com" value={slug} onChange={(e) => setSlug(e.target.value)} required />
          <Button type="submit" loading={create.isPending}>Add domain</Button>
        </form>
        {error && <p role="alert" className="mt-2 text-sm text-destructive">{error}</p>}
      </SettingsCard>

      {domains.isPending ? <Skeleton className="h-32 w-full" /> : domains.data?.length === 0 ? (
        <EmptyState icon={<Globe />} title="No custom domains yet" description="Links use shortie.link until you add your own domain." />
      ) : (
        <ul className="space-y-3">
          {domains.data?.map((d) => (
            <li key={d.id} className="rounded-xl border border-border bg-card p-4">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium">{d.slug}</span>
                {d.primary && <Badge variant="accent"><Star className="size-3" /> Primary</Badge>}
                {d.verified ? <Badge variant="success"><CheckCircle2 className="size-3" /> Verified</Badge> : <Badge variant="warning"><Clock className="size-3" /> Pending</Badge>}
                <div className="ml-auto flex gap-1">
                  {!d.verified && <Button size="sm" variant="secondary" loading={verify.isPending && verify.variables === d.id} onClick={() => verify.mutate(d.id)}>Verify</Button>}
                  {d.verified && !d.primary && <Button size="sm" variant="secondary" onClick={() => update.mutate({ id: d.id, primary: true })}>Make primary</Button>}
                  <Button size="icon" variant="ghost" aria-label={`Delete ${d.slug}`} onClick={() => setDeleting(d)}><Trash2 /></Button>
                </div>
              </div>
              {!d.verified && (
                <div className="mt-3 space-y-2 rounded-lg bg-muted p-3 text-sm">
                  <p>Add this DNS record at your registrar, then click <strong>Verify</strong>:</p>
                  <dl className="grid grid-cols-[auto_1fr_auto] items-center gap-x-4 gap-y-1 font-mono text-xs">
                    <dt className="text-muted-foreground">Type</dt><dd>{d.dns.type}</dd><span />
                    <dt className="text-muted-foreground">Name</dt><dd className="truncate">{d.dns.name}</dd><CopyButton value={d.dns.name} label="Copy name" />
                    <dt className="text-muted-foreground">Value</dt><dd className="truncate">{d.dns.value}</dd><CopyButton value={d.dns.value} label="Copy value" />
                  </dl>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}

      <ConfirmDialog open={!!deleting} onOpenChange={(o) => !o && setDeleting(null)} title={`Remove ${deleting?.slug}?`} description="You can only remove a domain once it has no links." confirmLabel="Remove" destructive loading={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting.id, { onSuccess: () => setDeleting(null), onSettled: () => setDeleting(null) })} />
    </>
  );
}
