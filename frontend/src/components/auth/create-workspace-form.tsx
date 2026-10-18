"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import * as React from "react";
import { useForm } from "react-hook-form";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { workspacesApi } from "@/lib/api";
import { useApiMutation } from "@/lib/hooks/use-api-mutation";
import { sessionKeys } from "@/lib/hooks/use-session";
import { applyApiErrors } from "@/lib/utils/forms";
import { workspaceSchema, type WorkspaceValues } from "@/lib/validation/auth";
import { AuthLayout } from "./auth-layout";

export function CreateWorkspaceForm() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [formError, setFormError] = React.useState<string | null>(null);
  const form = useForm<WorkspaceValues>({ resolver: zodResolver(workspaceSchema), defaultValues: { name: "", slug: "" } });
  const create = useApiMutation({
    mutationFn: (v: WorkspaceValues) => workspacesApi.create({ name: v.name, slug: v.slug || undefined }),
    silent: true,
    onSuccess: async (workspace) => {
      await queryClient.invalidateQueries({ queryKey: sessionKeys.workspaces });
      router.replace(`/app/${workspace.slug}/links`);
    },
  });

  return (
    <AuthLayout title="Create a workspace" description="Workspaces hold your links, domains, analytics and team." footer={<></>}>
      <form onSubmit={form.handleSubmit((v) => { setFormError(null); create.mutate(v, { onError: (e) => setFormError(applyApiErrors(e, form.setError)) }); })} className="space-y-4" noValidate>
        <Field label="Workspace name" htmlFor="name" error={form.formState.errors.name?.message}>
          <Input id="name" placeholder="Acme Inc." autoFocus {...form.register("name")} />
        </Field>
        <Field label="Workspace slug" htmlFor="slug" error={form.formState.errors.slug?.message} hint="Used in URLs. Leave blank to generate one.">
          <Input id="slug" placeholder="acme" {...form.register("slug")} />
        </Field>
        {formError && <p role="alert" className="rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive">{formError}</p>}
        <Button type="submit" className="w-full" loading={create.isPending}>Create workspace</Button>
      </form>
    </AuthLayout>
  );
}
