"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { CalendarClock, Eye, FlaskConical, Globe2, Tag, Target } from "lucide-react";
import * as React from "react";
import { Controller, FormProvider, useForm, useFormContext } from "react-hook-form";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent } from "@/components/ui/dialog";
import { Switch } from "@/components/ui/switch";
import { planAllows } from "@/lib/constants/plans";
import { useCreateLink, useUpdateLink } from "@/lib/hooks/use-links";
import { useDomains, useWorkspaceInfo } from "@/lib/hooks/use-library";
import { applyApiErrors } from "@/lib/utils/forms";
import { activeSections, emptyLinkForm, fromLink, linkFormSchema, toPayload, type LinkFormValues } from "@/lib/validation/link";
import type { Link } from "@/types/api";
import { AbTestFields } from "./link-form/abtest-fields";
import { AccessFields } from "./link-form/access-fields";
import { DestinationFields } from "./link-form/destination-fields";
import { OrganizeFields } from "./link-form/organize-fields";
import { PreviewFields } from "./link-form/preview-fields";
import { Section } from "./link-form/section";
import { TargetingFields } from "./link-form/targeting-fields";
import { UtmFields } from "./link-form/utm-fields";

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Present → edit mode. */
  link?: Link;
  /** Present (without `link`) → create a new link pre-filled from this one ("Duplicate"). */
  template?: Link;
}

export function LinkFormDialog({ open, onOpenChange, link, template }: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="max-w-2xl"
        title={link ? "Edit link" : "Create a new link"}
        description={link ? link.shortLink.replace("https://", "") : "Shorten a URL and add tracking, targeting and branding."}
      >
        {/* Mounted only while open so the form always starts from the right defaults. */}
        {open && <LinkForm link={link} template={template} onDone={() => onOpenChange(false)} />}
      </DialogContent>
    </Dialog>
  );
}

function LinkForm({ link, template, onDone }: { link?: Link; template?: Link; onDone: () => void }) {
  const isEdit = !!link;
  const workspace = useWorkspaceInfo();
  const domains = useDomains();
  const plan = workspace.data?.plan;
  const domainOptions = React.useMemo(
    () => [...(workspace.data?.systemDomains ?? []), ...(domains.data?.filter((d) => d.verified && !d.archived).map((d) => d.slug) ?? [])],
    [workspace.data, domains.data],
  );
  const primaryDomain = domains.data?.find((d) => d.primary && d.verified)?.slug;

  const initial = React.useMemo(() => {
    if (link) return fromLink(link);
    // A duplicate keeps everything except the short key (must be unique) and the password (never exposed by the API).
    if (template) return { ...fromLink(template), key: "" };
    return { ...emptyLinkForm, domain: primaryDomain ?? workspace.data?.systemDomains[0] ?? "" };
  }, [link, template, primaryDomain, workspace.data]);
  const form = useForm<LinkFormValues>({ resolver: zodResolver(linkFormSchema), defaultValues: initial });
  const [formError, setFormError] = React.useState<string | null>(null);
  const create = useCreateLink();
  const update = useUpdateLink();
  const sections = activeSections(initial);

  // The domain list loads asynchronously; adopt the right default once it is known (create mode only).
  React.useEffect(() => {
    if (!isEdit && !form.getValues("domain") && initial.domain) form.setValue("domain", initial.domain);
  }, [isEdit, initial.domain, form]);

  const onSubmit = form.handleSubmit((values) => {
    setFormError(null);
    const payload = toPayload(values, isEdit ? "edit" : "create", link?.hasPassword);
    const onError = (e: unknown) => setFormError(applyApiErrors(e, form.setError, { tagIds: "tagIds", testVariants: "variants" }));
    if (link) update.mutate({ id: link.id, ...payload }, { onSuccess: onDone, onError });
    else create.mutate(payload, { onSuccess: onDone, onError });
  });

  const pending = create.isPending || update.isPending;

  return (
    <FormProvider {...form}>
      <form onSubmit={onSubmit} className="space-y-5" noValidate>
        <DestinationFields domains={domainOptions.length ? domainOptions : [initial.domain].filter(Boolean)} isEdit={isEdit} />
        <OrganizeFields />

        <div className="space-y-2.5">
          <Section title="UTM parameters" description="Tag the destination for your analytics tools" icon={<Tag />} active={sections.utm} defaultOpen={sections.utm}>
            <UtmFields />
          </Section>
          <Section title="Expiration & password" description="Expire the link or require a password" icon={<CalendarClock />} locked={!planAllows(plan, "expiration")} active={sections.access} defaultOpen={sections.access}>
            <AccessFields passwordSet={!!link?.hasPassword} />
          </Section>
          <Section title="Targeting" description="Device deep links and per-country destinations" icon={<Target />} locked={!planAllows(plan, "geo_targeting")} active={sections.targeting} defaultOpen={sections.targeting}>
            <TargetingFields />
          </Section>
          <Section title="Link preview & cloaking" description="Customise how your link looks when shared" icon={<Eye />} locked={!planAllows(plan, "custom_preview")} active={sections.preview} defaultOpen={sections.preview}>
            <PreviewFields />
          </Section>
          <Section title="A/B test" description="Split traffic between destinations" icon={<FlaskConical />} locked={!planAllows(plan, "ab_testing")} active={sections.abtest} defaultOpen={sections.abtest}>
            <AbTestFields />
          </Section>
          <Section title="Tracking & indexing" description="Conversion tracking and search engine visibility" icon={<Globe2 />} locked={!planAllows(plan, "conversion_tracking")}>
            <ToggleRow name="trackConversion" label="Conversion tracking" hint="Attach a click id so leads and sales can be attributed to this link." />
            <ToggleRow name="doIndex" label="Allow search engine indexing" hint="By default short links are hidden from search results." />
          </Section>
        </div>

        {formError && <p role="alert" className="rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive">{formError}</p>}

        <div className="flex justify-end gap-2 border-t border-border pt-4">
          <Button type="button" variant="ghost" onClick={onDone}>Cancel</Button>
          <Button type="submit" loading={pending}>{isEdit ? "Save changes" : "Create link"}</Button>
        </div>
      </form>
    </FormProvider>
  );
}

function ToggleRow({ name, label, hint }: { name: "trackConversion" | "doIndex"; label: string; hint: string }) {
  const { control } = useFormContext<LinkFormValues>();
  return (
    <Controller
      control={control}
      name={name}
      render={({ field }) => (
        <div className="flex items-center justify-between gap-3">
          <div>
            <p className="text-sm font-medium">{label}</p>
            <p className="text-xs text-muted-foreground">{hint}</p>
          </div>
          <Switch checked={field.value} onCheckedChange={field.onChange} aria-label={label} />
        </div>
      )}
    />
  );
}
