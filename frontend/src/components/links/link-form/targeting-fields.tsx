"use client";

import { Plus, Trash2 } from "lucide-react";
import { useFieldArray, useFormContext } from "react-hook-form";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import type { LinkFormValues } from "@/lib/validation/link";

/** iOS / Android deep links and per-country destinations. */
export function TargetingFields() {
  const { register, control, formState: { errors } } = useFormContext<LinkFormValues>();
  const geo = useFieldArray({ control, name: "geo" });

  return (
    <div className="space-y-4">
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="iOS destination" htmlFor="link-ios" hint="Opens for iPhone & iPad visitors">
          <Input id="link-ios" placeholder="https://apps.apple.com/app/…" {...register("ios")} />
        </Field>
        <Field label="Android destination" htmlFor="link-android" hint="Opens for Android visitors">
          <Input id="link-android" placeholder="https://play.google.com/store/apps/…" {...register("android")} />
        </Field>
      </div>

      <fieldset className="space-y-2">
        <legend className="text-sm font-medium">Geo targeting</legend>
        {geo.fields.map((field, i) => (
          <div key={field.id} className="flex items-start gap-2">
            <Input aria-label="Country code" className="w-20 uppercase" maxLength={2} placeholder="US" aria-invalid={!!errors.geo?.[i]?.country} {...register(`geo.${i}.country`)} />
            <Input aria-label="Destination URL" placeholder="https://acme.com/us" aria-invalid={!!errors.geo?.[i]?.url} {...register(`geo.${i}.url`)} />
            <Button type="button" variant="ghost" size="icon" aria-label="Remove country" onClick={() => geo.remove(i)}><Trash2 /></Button>
          </div>
        ))}
        {errors.geo && <p role="alert" className="text-xs text-destructive">Each row needs a 2-letter country code and a URL.</p>}
        <Button type="button" variant="secondary" size="sm" onClick={() => geo.append({ country: "", url: "" })}><Plus /> Add country</Button>
      </fieldset>
    </div>
  );
}
