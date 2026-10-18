"use client";

import { Controller, useFormContext } from "react-hook-form";
import { Field } from "@/components/ui/field";
import { Select, Textarea } from "@/components/ui/input";
import { TagBadge } from "@/components/links/tag-badge";
import { useFolders, useTags } from "@/lib/hooks/use-library";
import { cn } from "@/lib/utils/cn";
import type { LinkFormValues } from "@/lib/validation/link";

/** Tags (toggle chips), folder and internal comments. */
export function OrganizeFields() {
  const { register, control } = useFormContext<LinkFormValues>();
  const tags = useTags();
  const folders = useFolders();

  return (
    <div className="space-y-4">
      <Field label="Tags" htmlFor="link-tags">
        <Controller
          control={control}
          name="tagIds"
          render={({ field }) => (
            <div id="link-tags" role="group" aria-label="Tags" className="flex flex-wrap gap-1.5">
              {tags.data?.length === 0 && <p className="text-sm text-muted-foreground">No tags yet — create some under Settings → Tags.</p>}
              {tags.data?.map((tag) => {
                const selected = field.value.includes(tag.id);
                return (
                  <button
                    key={tag.id}
                    type="button"
                    aria-pressed={selected}
                    onClick={() => field.onChange(selected ? field.value.filter((id) => id !== tag.id) : [...field.value, tag.id])}
                    className={cn("rounded-md ring-offset-2 ring-offset-card transition-shadow", selected ? "ring-2 ring-accent" : "opacity-70 hover:opacity-100")}
                  >
                    <TagBadge tag={tag} />
                  </button>
                );
              })}
            </div>
          )}
        />
      </Field>

      <Field label="Folder" htmlFor="link-folder">
        <Select id="link-folder" {...register("folderId")}>
          <option value="">No folder</option>
          {folders.data?.filter((f) => f.accessLevel === "write").map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
        </Select>
      </Field>

      <Field label="Comments" htmlFor="link-comments" hint="Only visible to your team">
        <Textarea id="link-comments" rows={2} {...register("comments")} />
      </Field>
    </div>
  );
}
