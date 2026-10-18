"use client";

import { Pencil, Tag as TagIcon, Trash2 } from "lucide-react";
import * as React from "react";
import { ConfirmDialog } from "@/components/forms/confirm-dialog";
import { TAG_COLORS, TagBadge } from "@/components/links/tag-badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Field } from "@/components/ui/field";
import { Input, Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api/client";
import { useTagMutations, useTags } from "@/lib/hooks/use-library";
import type { Tag, TagColor } from "@/types/api";
import { SettingsCard } from "./settings-card";

export function TagsSettings() {
  const tags = useTags();
  const { create, update, remove } = useTagMutations();
  const [editing, setEditing] = React.useState<Tag | "new" | null>(null);
  const [deleting, setDeleting] = React.useState<Tag | null>(null);

  return (
    <>
      <SettingsCard title="Tags" description="Label links to filter lists and analytics." actions={<Button size="sm" onClick={() => setEditing("new")}>New tag</Button>}>
        {tags.isPending ? <Skeleton className="h-24 w-full" /> : tags.data?.length === 0 ? (
          <EmptyState icon={<TagIcon />} title="No tags yet" description="Create your first tag to start organising links." className="py-8" />
        ) : (
          <ul className="divide-y divide-border">
            {tags.data?.map((tag) => (
              <li key={tag.id} className="flex items-center gap-3 py-2.5">
                <TagBadge tag={tag} />
                <div className="ml-auto flex">
                  <Button variant="ghost" size="icon" aria-label={`Edit ${tag.name}`} onClick={() => setEditing(tag)}><Pencil /></Button>
                  <Button variant="ghost" size="icon" aria-label={`Delete ${tag.name}`} onClick={() => setDeleting(tag)}><Trash2 /></Button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </SettingsCard>

      <TagDialog
        key={editing === null ? "closed" : editing === "new" ? "new" : editing.id}
        tag={editing === "new" ? undefined : editing ?? undefined}
        open={editing !== null}
        onOpenChange={(o) => !o && setEditing(null)}
        pending={create.isPending || update.isPending}
        onSubmit={(values, fail) => {
          const options = { onSuccess: () => setEditing(null), onError: fail };
          if (editing && editing !== "new") update.mutate({ id: editing.id, ...values }, options);
          else create.mutate(values, options);
        }}
      />
      <ConfirmDialog open={!!deleting} onOpenChange={(o) => !o && setDeleting(null)} title={`Delete “${deleting?.name}”?`} description="It will be removed from every link that uses it." confirmLabel="Delete" destructive loading={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })} />
    </>
  );
}

function TagDialog({ tag, open, onOpenChange, pending, onSubmit }: { tag?: Tag; open: boolean; onOpenChange: (o: boolean) => void; pending: boolean; onSubmit: (v: { name: string; color: TagColor }, fail: (e: unknown) => void) => void }) {
  const [name, setName] = React.useState(tag?.name ?? "");
  const [color, setColor] = React.useState<TagColor>(tag?.color ?? "blue");
  const [error, setError] = React.useState<string | null>(null);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent title={tag ? "Edit tag" : "New tag"} className="max-w-sm">
        <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); setError(null); onSubmit({ name, color }, (err) => setError(err instanceof ApiError ? err.message : "Could not save the tag.")); }}>
          <Field label="Name" htmlFor="tag-name" error={error ?? undefined}><Input id="tag-name" required maxLength={50} autoFocus value={name} onChange={(e) => setName(e.target.value)} /></Field>
          <Field label="Colour" htmlFor="tag-color"><Select id="tag-color" value={color} onChange={(e) => setColor(e.target.value as TagColor)}>{TAG_COLORS.map((c) => <option key={c} value={c}>{c[0].toUpperCase() + c.slice(1)}</option>)}</Select></Field>
          <div className="flex items-center justify-between"><TagBadge tag={{ name: name || "Preview", color }} /><Button type="submit" loading={pending}>{tag ? "Save" : "Create"}</Button></div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
