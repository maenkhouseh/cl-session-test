import { z } from "zod";
import type { Link, LinkInput } from "@/types/api";

export interface GeoRow {
  country: string;
  url: string;
}

export interface VariantRow {
  url: string;
  percentage: number;
}

/** Everything the link dialog edits. Strings stay strings ("" = unset); conversion to the API shape is in `toPayload`. */
export interface LinkFormValues {
  url: string;
  domain: string;
  key: string;
  tagIds: string[];
  folderId: string;
  comments: string;
  utm_source: string;
  utm_medium: string;
  utm_campaign: string;
  utm_term: string;
  utm_content: string;
  ref: string;
  expiresAt: string;
  expiredUrl: string;
  password: string;
  ios: string;
  android: string;
  geo: GeoRow[];
  proxy: boolean;
  title: string;
  description: string;
  image: string;
  rewrite: boolean;
  trackConversion: boolean;
  doIndex: boolean;
  abEnabled: boolean;
  variants: VariantRow[];
}

export const emptyLinkForm: LinkFormValues = {
  url: "", domain: "", key: "", tagIds: [], folderId: "", comments: "",
  utm_source: "", utm_medium: "", utm_campaign: "", utm_term: "", utm_content: "", ref: "",
  expiresAt: "", expiredUrl: "", password: "", ios: "", android: "", geo: [],
  proxy: false, title: "", description: "", image: "", rewrite: false, trackConversion: false, doIndex: false,
  abEnabled: false, variants: [{ url: "", percentage: 50 }, { url: "", percentage: 50 }],
};

export const linkFormSchema = z
  .object({
    url: z.string().trim().min(1, "Enter a destination URL").max(2048),
    domain: z.string(),
    key: z.string().trim().max(190).regex(/^$|^[A-Za-z0-9][A-Za-z0-9_\-/]*$/, "Use letters, numbers, dashes, underscores or slashes"),
    tagIds: z.array(z.string()).max(50),
    folderId: z.string(),
    comments: z.string().max(5000),
    utm_source: z.string().max(190), utm_medium: z.string().max(190), utm_campaign: z.string().max(190),
    utm_term: z.string().max(190), utm_content: z.string().max(190), ref: z.string().max(190),
    expiresAt: z.string(),
    expiredUrl: z.string().max(2048),
    password: z.string().max(100),
    ios: z.string().max(2048),
    android: z.string().max(2048),
    geo: z.array(z.object({ country: z.string(), url: z.string() })),
    proxy: z.boolean(),
    title: z.string().max(120),
    description: z.string().max(240),
    image: z.string().max(2048),
    rewrite: z.boolean(),
    trackConversion: z.boolean(),
    doIndex: z.boolean(),
    abEnabled: z.boolean(),
    variants: z.array(z.object({ url: z.string(), percentage: z.number() })),
  })
  .superRefine((v, ctx) => {
    v.geo.forEach((row, i) => {
      if (!row.country && !row.url) return;
      if (!/^[A-Za-z]{2}$/.test(row.country)) ctx.addIssue({ code: "custom", path: ["geo", i, "country"], message: "2-letter country code" });
      if (!row.url.trim()) ctx.addIssue({ code: "custom", path: ["geo", i, "url"], message: "Enter a URL" });
    });

    if (v.abEnabled) {
      v.variants.forEach((row, i) => {
        if (!row.url.trim()) ctx.addIssue({ code: "custom", path: ["variants", i, "url"], message: "Enter a URL" });
      });
      const total = v.variants.reduce((sum, row) => sum + (Number(row.percentage) || 0), 0);
      if (total !== 100) ctx.addIssue({ code: "custom", path: ["variants"], message: `Percentages must add up to 100 (currently ${total})` });
    }
  });

const orNull = (value: string): string | null => (value.trim() === "" ? null : value.trim());

/** `<input type="datetime-local">` value (local time) ↔ ISO-8601 UTC. */
export function localToIso(local: string): string | null {
  return local ? new Date(local).toISOString() : null;
}

export function isoToLocal(iso: string | null): string {
  if (!iso) return "";
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/**
 * Form → API payload.
 * On create, empty optional fields are omitted. On edit they are sent as `null` so a user can clear them.
 */
export function toPayload(values: LinkFormValues, mode: "create" | "edit", originalHadPassword = false): LinkInput {
  const clear = <T,>(value: T | null): T | null | undefined => (value === null && mode === "create" ? undefined : value);
  const geoEntries = values.geo.filter((g) => g.country && g.url.trim());

  const payload: LinkInput = {
    url: values.url.trim(),
    domain: values.domain || undefined,
    key: values.key.trim() || undefined,
    tagIds: values.tagIds,
    folderId: clear(orNull(values.folderId)),
    comments: clear(orNull(values.comments)),
    utm_source: clear(orNull(values.utm_source)),
    utm_medium: clear(orNull(values.utm_medium)),
    utm_campaign: clear(orNull(values.utm_campaign)),
    utm_term: clear(orNull(values.utm_term)),
    utm_content: clear(orNull(values.utm_content)),
    ref: clear(orNull(values.ref)),
    expiresAt: clear(localToIso(values.expiresAt)),
    expiredUrl: clear(orNull(values.expiredUrl)),
    ios: clear(orNull(values.ios)),
    android: clear(orNull(values.android)),
    geo: geoEntries.length ? Object.fromEntries(geoEntries.map((g) => [g.country.toUpperCase(), g.url.trim()])) : clear(null),
    proxy: values.proxy,
    title: clear(orNull(values.title)),
    description: clear(orNull(values.description)),
    image: clear(orNull(values.image)),
    rewrite: values.rewrite,
    trackConversion: values.trackConversion,
    doIndex: values.doIndex,
    testVariants: values.abEnabled ? values.variants.map((v) => ({ url: v.url.trim(), percentage: Number(v.percentage) })) : clear(null),
  };

  // Password: a typed value sets it; on edit, leaving it blank keeps the current one (the API never returns it).
  if (values.password) payload.password = values.password;
  else if (mode === "edit" && !originalHadPassword) payload.password = null;

  return Object.fromEntries(Object.entries(payload).filter(([, v]) => v !== undefined)) as unknown as LinkInput;
}

export function fromLink(link: Link): LinkFormValues {
  return {
    url: link.url,
    domain: link.domain,
    key: link.key,
    tagIds: link.tags.map((t) => t.id),
    folderId: link.folderId ?? "",
    comments: link.comments ?? "",
    utm_source: link.utm_source ?? "", utm_medium: link.utm_medium ?? "", utm_campaign: link.utm_campaign ?? "",
    utm_term: link.utm_term ?? "", utm_content: link.utm_content ?? "", ref: link.ref ?? "",
    expiresAt: isoToLocal(link.expiresAt),
    expiredUrl: link.expiredUrl ?? "",
    password: "",
    ios: link.ios ?? "",
    android: link.android ?? "",
    geo: Object.entries(link.geo ?? {}).map(([country, url]) => ({ country, url })),
    proxy: link.proxy,
    title: link.title ?? "",
    description: link.description ?? "",
    image: link.image ?? "",
    rewrite: link.rewrite,
    trackConversion: link.trackConversion,
    doIndex: link.doIndex,
    abEnabled: !!link.testVariants?.length,
    variants: link.testVariants?.length ? link.testVariants.map((v) => ({ ...v })) : emptyLinkForm.variants.map((v) => ({ ...v })),
  };
}

/** Which advanced sections already hold data (so edit mode opens them). */
export function activeSections(values: LinkFormValues): Record<"utm" | "access" | "targeting" | "preview" | "abtest", boolean> {
  return {
    utm: !!(values.utm_source || values.utm_medium || values.utm_campaign || values.utm_term || values.utm_content || values.ref),
    access: !!(values.expiresAt || values.password || values.expiredUrl),
    targeting: !!(values.ios || values.android || values.geo.length),
    preview: !!(values.proxy || values.title || values.description || values.image),
    abtest: values.abEnabled,
  };
}
