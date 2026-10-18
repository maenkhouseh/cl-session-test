"use client";

import { Plus, Trash2 } from "lucide-react";
import { Controller, useFieldArray, useFormContext } from "react-hook-form";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import type { LinkFormValues } from "@/lib/validation/link";

export function AbTestFields() {
  const { register, control, watch, setValue, formState: { errors } } = useFormContext<LinkFormValues>();
  const variants = useFieldArray({ control, name: "variants" });
  const enabled = watch("abEnabled");
  const total = watch("variants").reduce((sum, v) => sum + (Number(v.percentage) || 0), 0);

  const rebalance = (count: number) => {
    const share = Math.floor(100 / count);
    for (let i = 0; i < count; i++) setValue(`variants.${i}.percentage`, i === count - 1 ? 100 - share * (count - 1) : share, { shouldDirty: true });
  };

  return (
    <div className="space-y-4">
      <Controller
        control={control}
        name="abEnabled"
        render={({ field }) => (
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="text-sm font-medium">Enable A/B test</p>
              <p className="text-xs text-muted-foreground">Split traffic between 2–4 destinations. Visitors keep seeing the same variant.</p>
            </div>
            <Switch checked={field.value} onCheckedChange={field.onChange} aria-label="Enable A/B test" />
          </div>
        )}
      />
      {enabled && (
        <div className="space-y-2">
          {variants.fields.map((field, i) => (
            <div key={field.id} className="flex items-start gap-2">
              <span className="flex h-9 w-6 items-center justify-center text-xs font-semibold text-muted-foreground">{String.fromCharCode(65 + i)}</span>
              <Input aria-label={`Variant ${i + 1} URL`} placeholder="https://acme.com/variant" aria-invalid={!!errors.variants?.[i]?.url} {...register(`variants.${i}.url`)} />
              <div className="relative w-24 shrink-0">
                <Input aria-label={`Variant ${i + 1} percentage`} type="number" min={1} max={99} className="pr-7" {...register(`variants.${i}.percentage`, { valueAsNumber: true })} />
                <span className="pointer-events-none absolute right-3 top-2 text-sm text-muted-foreground">%</span>
              </div>
              <Button type="button" variant="ghost" size="icon" aria-label="Remove variant" disabled={variants.fields.length <= 2} onClick={() => { variants.remove(i); rebalance(variants.fields.length - 1); }}><Trash2 /></Button>
            </div>
          ))}
          <div className="flex items-center justify-between">
            <Button type="button" variant="secondary" size="sm" disabled={variants.fields.length >= 4} onClick={() => { variants.append({ url: "", percentage: 0 }); rebalance(variants.fields.length + 1); }}><Plus /> Add variant</Button>
            <span className={total === 100 ? "text-xs text-muted-foreground" : "text-xs font-medium text-destructive"} role="status">Total {total}%</span>
          </div>
          {errors.variants?.root?.message || (errors.variants as { message?: string } | undefined)?.message ? (
            <p role="alert" className="text-xs text-destructive">{(errors.variants as { message?: string }).message ?? errors.variants?.root?.message}</p>
          ) : null}
        </div>
      )}
    </div>
  );
}
