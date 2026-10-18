"use client";

import { useQuery } from "@tanstack/react-query";
import * as React from "react";
import { CopyButton } from "@/components/forms/copy-button";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent } from "@/components/ui/dialog";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { useApiMutation } from "@/lib/hooks/use-api-mutation";
import { useWorkspaceApi } from "@/lib/hooks/use-workspace-api";
import type { Link } from "@/types/api";

/** Owner-side controls for a link's public analytics dashboard. */
export function LinkShareDialog({ link, open, onOpenChange }: { link: Link | null; open: boolean; onOpenChange: (open: boolean) => void }) {
  if (!link) return null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent title="Share analytics" description="Give partners, clients or investors a read-only dashboard for this link." className="max-w-md">
        {open && <ShareBody link={link} />}
      </DialogContent>
    </Dialog>
  );
}

function ShareBody({ link }: { link: Link }) {
  const { slug, api } = useWorkspaceApi();
  const key = ["ws", slug, "links", link.id, "share"];
  const share = useQuery({ queryKey: key, queryFn: () => api.links.share.get(link.id) });
  const [password, setPassword] = React.useState("");

  const save = useApiMutation({ mutationFn: (body: { password?: string | null; doIndex?: boolean }) => api.links.share.save(link.id, body), invalidate: [key], success: "Sharing updated" });
  const remove = useApiMutation({ mutationFn: () => api.links.share.remove(link.id), invalidate: [key], success: "Public dashboard disabled" });

  const enabled = !!share.data;
  const url = enabled && typeof window !== "undefined" ? `${window.location.origin}/share/${share.data!.id}` : "";

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-medium">Enable public sharing</p>
          <p className="text-xs text-muted-foreground">Anyone with the link can view clicks, locations and devices.</p>
        </div>
        <Switch
          checked={enabled}
          disabled={share.isPending || save.isPending || remove.isPending}
          onCheckedChange={(on) => (on ? save.mutate({}) : remove.mutate())}
          aria-label="Enable public sharing"
        />
      </div>

      {enabled && (
        <>
          <Field label="Public link" htmlFor="share-url">
            <div className="flex gap-1">
              <Input id="share-url" readOnly value={url} onFocus={(e) => e.currentTarget.select()} />
              <CopyButton value={url} label="Copy public link" message="Public link copied" />
            </div>
          </Field>
          <Field label="Password" htmlFor="share-password" hint={share.data!.passwordProtected ? "A password is required to view the dashboard." : "Optional — require a password to view."}>
            <div className="flex gap-2">
              <Input id="share-password" type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder={share.data!.passwordProtected ? "••••••••" : ""} />
              <Button variant="secondary" disabled={save.isPending || (!password && !share.data!.passwordProtected)} onClick={() => save.mutate({ password: password || null }, { onSuccess: () => setPassword("") })}>
                {password || !share.data!.passwordProtected ? "Set" : "Remove"}
              </Button>
            </div>
          </Field>
          <div className="flex items-center justify-between gap-3">
            <p className="text-sm">Allow search engine indexing</p>
            <Switch checked={share.data!.doIndex} onCheckedChange={(doIndex) => save.mutate({ doIndex })} aria-label="Allow search engine indexing" />
          </div>
        </>
      )}
    </div>
  );
}
