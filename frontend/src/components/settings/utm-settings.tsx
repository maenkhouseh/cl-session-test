"use client";

import { Trash2 } from "lucide-react";
import * as React from "react";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api/client";
import { useUtmTemplateMutations, useUtmTemplates } from "@/lib/hooks/use-library";
import { SettingsCard } from "./settings-card";

const FIELDS = [
  ["utm_source", "Source"], ["utm_medium", "Medium"], ["utm_campaign", "Campaign"], ["utm_term", "Term"], ["utm_content", "Content"], ["ref", "Referral"],
] as const;

type Values = { name: string } & Record<(typeof FIELDS)[number][0], string>;
const empty: Values = { name: "", utm_source: "", utm_medium: "", utm_campaign: "", utm_term: "", utm_content: "", ref: "" };

export function UtmSettings() {
  const templates = useUtmTemplates();
  const { create, remove } = useUtmTemplateMutations();
  const [values, setValues] = React.useState<Values>(empty);
  const [error, setError] = React.useState<string | null>(null);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    const body = Object.fromEntries(Object.entries(values).map(([k, v]) => [k, v || null])) as Values;
    create.mutate({ ...body, name: values.name }, { onSuccess: () => setValues(empty), onError: (err) => setError(err instanceof ApiError ? err.message : "Could not save the template.") });
  };

  return (
    <>
      <SettingsCard title="New UTM template" description="Reusable presets so every campaign link is tagged consistently.">
        <form className="grid gap-4 sm:grid-cols-2" onSubmit={submit}>
          <Field label="Template name" htmlFor="utm-name" className="sm:col-span-2" error={error ?? undefined}>
            <Input id="utm-name" required value={values.name} onChange={(e) => setValues({ ...values, name: e.target.value })} />
          </Field>
          {FIELDS.map(([key, label]) => (
            <Field key={key} label={label} htmlFor={`tpl-${key}`}><Input id={`tpl-${key}`} value={values[key]} onChange={(e) => setValues({ ...values, [key]: e.target.value })} /></Field>
          ))}
          <div className="sm:col-span-2"><Button type="submit" loading={create.isPending}>Create template</Button></div>
        </form>
      </SettingsCard>

      <SettingsCard title="Templates">
        {templates.isPending ? <Skeleton className="h-20 w-full" /> : templates.data?.length === 0 ? <p className="text-sm text-muted-foreground">No templates yet.</p> : (
          <ul className="divide-y divide-border">
            {templates.data?.map((t) => (
              <li key={t.id} className="flex items-center gap-3 py-2.5">
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium">{t.name}</p>
                  <p className="truncate font-mono text-xs text-muted-foreground">{FIELDS.filter(([k]) => t[k]).map(([k]) => `${k}=${t[k]}`).join("  ") || "(empty)"}</p>
                </div>
                <Button variant="ghost" size="icon" aria-label={`Delete ${t.name}`} onClick={() => remove.mutate(t.id)}><Trash2 /></Button>
              </li>
            ))}
          </ul>
        )}
      </SettingsCard>
    </>
  );
}
