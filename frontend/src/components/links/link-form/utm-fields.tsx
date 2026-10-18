"use client";

import { useFormContext } from "react-hook-form";
import { Field } from "@/components/ui/field";
import { Input, Select } from "@/components/ui/input";
import { useUtmTemplates } from "@/lib/hooks/use-library";
import type { LinkFormValues } from "@/lib/validation/link";

const UTM_FIELDS = [
  { name: "utm_source", label: "Source", placeholder: "google" },
  { name: "utm_medium", label: "Medium", placeholder: "cpc" },
  { name: "utm_campaign", label: "Campaign", placeholder: "summer_sale" },
  { name: "utm_term", label: "Term", placeholder: "running shoes" },
  { name: "utm_content", label: "Content", placeholder: "logolink" },
  { name: "ref", label: "Referral", placeholder: "friend" },
] as const;

export function UtmFields() {
  const { register, setValue } = useFormContext<LinkFormValues>();
  const templates = useUtmTemplates();

  return (
    <div className="space-y-4">
      {!!templates.data?.length && (
        <Field label="Template" htmlFor="utm-template">
          <Select
            id="utm-template"
            defaultValue=""
            onChange={(e) => {
              const t = templates.data?.find((x) => x.id === e.target.value);
              if (!t) return;
              for (const { name } of UTM_FIELDS) setValue(name, t[name] ?? "", { shouldDirty: true });
            }}
          >
            <option value="">Apply a template…</option>
            {templates.data.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
          </Select>
        </Field>
      )}
      <div className="grid gap-4 sm:grid-cols-2">
        {UTM_FIELDS.map(({ name, label, placeholder }) => (
          <Field key={name} label={label} htmlFor={`link-${name}`}>
            <Input id={`link-${name}`} placeholder={placeholder} {...register(name)} />
          </Field>
        ))}
      </div>
    </div>
  );
}
