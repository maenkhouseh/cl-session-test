"use client";

import { Shuffle } from "lucide-react";
import { useFormContext } from "react-hook-form";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input, Select } from "@/components/ui/input";
import type { LinkFormValues } from "@/lib/validation/link";

/** Destination URL + short link (domain / key). */
export function DestinationFields({ domains, isEdit }: { domains: string[]; isEdit: boolean }) {
  const { register, setValue, formState: { errors } } = useFormContext<LinkFormValues>();

  return (
    <div className="space-y-4">
      <Field label="Destination URL" htmlFor="link-url" error={errors.url?.message}>
        <Input id="link-url" type="text" inputMode="url" placeholder="https://acme.com/landing-page" autoFocus={!isEdit} aria-invalid={!!errors.url} {...register("url")} />
      </Field>

      <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <Field label="Domain" htmlFor="link-domain">
          <Select id="link-domain" {...register("domain")}>
            {domains.map((d) => <option key={d} value={d}>{d}</option>)}
          </Select>
        </Field>
        <Field label="Short link" htmlFor="link-key" error={errors.key?.message} hint={isEdit ? undefined : "Leave blank to generate one"}>
          <div className="flex gap-2">
            <Input id="link-key" placeholder="(auto)" aria-invalid={!!errors.key} {...register("key")} />
            <Button type="button" variant="secondary" size="icon" aria-label="Clear key to auto-generate" title="Auto-generate" onClick={() => setValue("key", "", { shouldDirty: true })}>
              <Shuffle />
            </Button>
          </div>
        </Field>
      </div>
    </div>
  );
}
