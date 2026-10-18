"use client";

import { Folder as FolderIcon, Pencil, Trash2 } from "lucide-react";
import * as React from "react";
import { ConfirmDialog } from "@/components/forms/confirm-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Field } from "@/components/ui/field";
import { Input, Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api/client";
import { planAllows } from "@/lib/constants/plans";
import { useFolderMutations, useFolders, useWorkspaceInfo } from "@/lib/hooks/use-library";
import type { Folder } from "@/types/api";
import { SettingsCard } from "./settings-card";

export function FoldersSettings() {
  const folders = useFolders();
  const info = useWorkspaceInfo();
  const { create, update, remove } = useFolderMutations();
  const [editing, setEditing] = React.useState<Folder | "new" | null>(null);
  const [deleting, setDeleting] = React.useState<Folder | null>(null);
  const available = (info.data?.limits.folders ?? 0) !== 0;

  return (
    <>
      <SettingsCard title="Folders" description="Group links and control who can edit them." actions={<Button size="sm" disabled={!available} onClick={() => setEditing("new")}>New folder</Button>}>
        {!available && info.data && <p className="mb-3 rounded-lg bg-warning/10 px-3 py-2 text-sm text-warning">Folders aren&apos;t available on the {info.data.plan} plan. {planAllows(info.data.plan, "webhooks") ? "" : "Upgrade to organise links into folders."}</p>}
        {folders.isPending ? <Skeleton className="h-24 w-full" /> : folders.data?.length === 0 ? (
          <EmptyState icon={<FolderIcon />} title="No folders yet" className="py-8" />
        ) : (
          <ul className="divide-y divide-border">
            {folders.data?.map((f) => (
              <li key={f.id} className="flex items-center gap-3 py-2.5">
                <FolderIcon className="size-4 text-muted-foreground" />
                <div className="min-w-0 flex-1"><p className="truncate text-sm font-medium">{f.name}</p>{f.description && <p className="truncate text-xs text-muted-foreground">{f.description}</p>}</div>
                <Badge>{f.linkCount ?? 0} links</Badge>
                <Badge variant={f.accessLevel === "write" ? "success" : "warning"}>{f.accessLevel === "write" ? "Can edit" : f.accessLevel === "read" ? "Read only" : "No access"}</Badge>
                <Button variant="ghost" size="icon" aria-label={`Edit ${f.name}`} onClick={() => setEditing(f)}><Pencil /></Button>
                <Button variant="ghost" size="icon" aria-label={`Delete ${f.name}`} onClick={() => setDeleting(f)}><Trash2 /></Button>
              </li>
            ))}
          </ul>
        )}
      </SettingsCard>

      <FolderDialog
        key={editing === null ? "closed" : editing === "new" ? "new" : editing.id}
        folder={editing === "new" ? undefined : editing ?? undefined}
        open={editing !== null}
        onOpenChange={(o) => !o && setEditing(null)}
        pending={create.isPending || update.isPending}
        onSubmit={(values, fail) => {
          const options = { onSuccess: () => setEditing(null), onError: fail };
          if (editing && editing !== "new") update.mutate({ id: editing.id, ...values }, options);
          else create.mutate(values, options);
        }}
      />
      <ConfirmDialog open={!!deleting} onOpenChange={(o) => !o && setDeleting(null)} title={`Delete “${deleting?.name}”?`} description="Links in this folder are kept and become unfiled." confirmLabel="Delete" destructive loading={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })} />
    </>
  );
}

function FolderDialog({ folder, open, onOpenChange, pending, onSubmit }: { folder?: Folder; open: boolean; onOpenChange: (o: boolean) => void; pending: boolean; onSubmit: (v: { name: string; description: string | null; accessLevel: string }, fail: (e: unknown) => void) => void }) {
  const [name, setName] = React.useState(folder?.name ?? "");
  const [description, setDescription] = React.useState(folder?.description ?? "");
  const [accessLevel, setAccessLevel] = React.useState(folder?.accessLevel ?? "write");
  const [error, setError] = React.useState<string | null>(null);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent title={folder ? "Edit folder" : "New folder"} className="max-w-sm">
        <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); setError(null); onSubmit({ name, description: description || null, accessLevel }, (err) => setError(err instanceof ApiError ? err.message : "Could not save the folder.")); }}>
          <Field label="Name" htmlFor="folder-name" error={error ?? undefined}><Input id="folder-name" required autoFocus value={name} onChange={(e) => setName(e.target.value)} /></Field>
          <Field label="Description" htmlFor="folder-desc"><Input id="folder-desc" value={description} onChange={(e) => setDescription(e.target.value)} /></Field>
          <Field label="Member access" htmlFor="folder-access" hint="What teammates can do with links in this folder">
            <Select id="folder-access" value={accessLevel} onChange={(e) => setAccessLevel(e.target.value as Folder["accessLevel"])}><option value="write">Can edit</option><option value="read">Read only</option><option value="none">No access</option></Select>
          </Field>
          <div className="flex justify-end"><Button type="submit" loading={pending}>{folder ? "Save" : "Create"}</Button></div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
