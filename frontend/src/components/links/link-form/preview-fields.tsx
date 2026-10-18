"use client";

import { Controller, useFormContext } from "react-hook-form";
import { Field } from "@/components/ui/field";
import { Input, Textarea } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import type { LinkFormValues } from "@/lib/validation/link";

/** Custom social preview (Open Graph) and cloaking. */
export function PreviewFields() {
  const { register, control, watch } = useFormContext<LinkFormValues>();
  const [title, description, image] = watch(["title", "description", "image"]);

  return (
    <div className="space-y-4">
      <Controller
        control={control}
        name="proxy"
        render={({ field }) => (
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="text-sm font-medium">Custom link preview</p>
              <p className="text-xs text-muted-foreground">Override the title, description and image shown on social networks.</p>
            </div>
            <Switch checked={field.value} onCheckedChange={field.onChange} aria-label="Custom link preview" />
          </div>
        )}
      />
      <Field label="Title" htmlFor="link-title">
        <Input id="link-title" maxLength={120} {...register("title")} />
      </Field>
      <Field label="Description" htmlFor="link-description">
        <Textarea id="link-description" rows={2} maxLength={240} {...register("description")} />
      </Field>
      <Field label="Image URL" htmlFor="link-image">
        <Input id="link-image" placeholder="https://acme.com/og.png" {...register("image")} />
      </Field>
      {(title || description || image) && (
        <div className="overflow-hidden rounded-lg border border-border" aria-label="Preview">
          {image && <img src={image} alt="" className="aspect-[1.91/1] w-full bg-muted object-cover" />}
          <div className="p-3">
            <p className="truncate text-sm font-semibold">{title || "Untitled"}</p>
            {description && <p className="line-clamp-2 text-xs text-muted-foreground">{description}</p>}
          </div>
        </div>
      )}
      <Controller
        control={control}
        name="rewrite"
        render={({ field }) => (
          <div className="flex items-center justify-between gap-3 border-t border-border pt-4">
            <div>
              <p className="text-sm font-medium">Link cloaking</p>
              <p className="text-xs text-muted-foreground">Keep your short URL in the address bar by showing the destination in a frame.</p>
            </div>
            <Switch checked={field.value} onCheckedChange={field.onChange} aria-label="Link cloaking" />
          </div>
        )}
      />
    </div>
  );
}
