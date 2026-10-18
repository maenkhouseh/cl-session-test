"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useRouter } from "next/navigation";
import * as React from "react";
import { useForm } from "react-hook-form";
import { ConfirmDialog } from "@/components/forms/confirm-dialog";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { useApiMutation } from "@/lib/hooks/use-api-mutation";
import { useWorkspaceInfo } from "@/lib/hooks/use-library";
import { sessionKeys, useWorkspaces } from "@/lib/hooks/use-session";
import { useWorkspaceApi } from "@/lib/hooks/use-workspace-api";
import { applyApiErrors } from "@/lib/utils/forms";
import { workspaceSchema, type WorkspaceValues } from "@/lib/validation/auth";
import { SettingsCard } from "./settings-card";

export function GeneralSettings() {
  const { slug, api } = useWorkspaceApi();
  const router = useRouter();
  const info = useWorkspaceInfo();
  const workspaces = useWorkspaces();
  const isOwner = workspaces.data?.find((w) => w.slug === slug)?.role === "owner";
  const [confirming, setConfirming] = React.useState(false);
  const form = useForm<WorkspaceValues>({ resolver: zodResolver(workspaceSchema), values: info.data ? { name: info.data.name, slug: info.data.slug } : undefined });

  const update = useApiMutation({
    mutationFn: (v: WorkspaceValues) => api.workspace.update({ name: v.name, slug: v.slug || undefined }),
    invalidate: [sessionKeys.workspaces, ["ws", slug, "workspace"]],
    success: "Workspace updated",
    silent: true,
    onSuccess: (w) => { if (w.slug !== slug) router.replace(`/app/${w.slug}/settings/general`); },
  });
  const remove = useApiMutation({
    mutationFn: () => api.workspace.remove(),
    invalidate: [sessionKeys.workspaces],
    success: "Workspace deleted",
    onSuccess: () => router.replace("/app"),
  });

  return (
    <>
      <SettingsCard title="Workspace" description="Your workspace name and URL slug.">
        <form className="max-w-md space-y-4" noValidate onSubmit={form.handleSubmit((v) => update.mutate(v, { onError: (e) => applyApiErrors(e, form.setError) }))}>
          <Field label="Name" htmlFor="ws-name" error={form.formState.errors.name?.message}>
            <Input id="ws-name" disabled={!isOwner} {...form.register("name")} />
          </Field>
          <Field label="Slug" htmlFor="ws-slug" error={form.formState.errors.slug?.message} hint="Appears in your dashboard URL">
            <Input id="ws-slug" disabled={!isOwner} {...form.register("slug")} />
          </Field>
          {isOwner ? <Button type="submit" loading={update.isPending} disabled={!form.formState.isDirty}>Save changes</Button> : <p className="text-sm text-muted-foreground">Only the workspace owner can change these settings.</p>}
        </form>
      </SettingsCard>

      {isOwner && (
        <SettingsCard title="Delete workspace" description="Permanently delete this workspace, its links and analytics. This cannot be undone.">
          <Button variant="destructive" onClick={() => setConfirming(true)}>Delete workspace</Button>
          <ConfirmDialog open={confirming} onOpenChange={setConfirming} title="Delete this workspace?" description={`All links in “${info.data?.name}” will stop working immediately.`} confirmLabel="Delete workspace" destructive loading={remove.isPending} onConfirm={() => remove.mutate()} />
        </SettingsCard>
      )}
    </>
  );
}
