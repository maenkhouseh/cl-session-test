import { describe, expect, it } from "vitest";
import type { Link } from "@/types/api";
import { activeSections, emptyLinkForm, fromLink, isoToLocal, linkFormSchema, localToIso, toPayload } from "./link";

const form = (over: Partial<typeof emptyLinkForm> = {}) => ({ ...emptyLinkForm, url: "https://example.com", ...over });

describe("linkFormSchema", () => {
  it("accepts a minimal link", () => {
    expect(linkFormSchema.safeParse(form()).success).toBe(true);
  });

  it("requires a destination", () => {
    const r = linkFormSchema.safeParse(form({ url: "  " }));
    expect(r.success).toBe(false);
  });

  it.each(["has space", "-lead", "bad!"])("rejects the key %j", (key) => {
    expect(linkFormSchema.safeParse(form({ key })).success).toBe(false);
  });

  it.each(["promo", "docs/start", "A_b-1"])("accepts the key %j", (key) => {
    expect(linkFormSchema.safeParse(form({ key })).success).toBe(true);
  });

  it("validates geo rows but ignores completely empty ones", () => {
    expect(linkFormSchema.safeParse(form({ geo: [{ country: "", url: "" }] })).success).toBe(true);
    expect(linkFormSchema.safeParse(form({ geo: [{ country: "USA", url: "https://x.com" }] })).success).toBe(false);
    expect(linkFormSchema.safeParse(form({ geo: [{ country: "US", url: "" }] })).success).toBe(false);
  });

  it("requires A/B variants to sum to 100", () => {
    const variants = (a: number, b: number) => [{ url: "https://a.com", percentage: a }, { url: "https://b.com", percentage: b }];
    expect(linkFormSchema.safeParse(form({ abEnabled: true, variants: variants(50, 50) })).success).toBe(true);
    const bad = linkFormSchema.safeParse(form({ abEnabled: true, variants: variants(60, 60) }));
    expect(bad.success).toBe(false);
    expect(JSON.stringify(bad.error?.issues)).toContain("currently 120");
    // Disabled A/B tests are not validated at all.
    expect(linkFormSchema.safeParse(form({ abEnabled: false, variants: variants(1, 1) })).success).toBe(true);
  });
});

describe("toPayload", () => {
  it("omits empty optional fields on create", () => {
    const payload = toPayload(form({ key: "promo" }), "create");
    expect(payload).toMatchObject({ url: "https://example.com", key: "promo", tagIds: [], proxy: false });
    for (const field of ["folderId", "comments", "title", "expiresAt", "geo", "testVariants", "password", "utm_source"]) {
      expect(payload).not.toHaveProperty(field);
    }
  });

  it("sends null on edit so fields can be cleared", () => {
    const payload = toPayload(form(), "edit");
    expect(payload).toMatchObject({ folderId: null, comments: null, title: null, expiresAt: null, geo: null, testVariants: null, utm_source: null, password: null });
  });

  it("keeps an existing password on edit when the field is left blank", () => {
    expect(toPayload(form(), "edit", true)).not.toHaveProperty("password");
    expect(toPayload(form({ password: "new" }), "edit", true).password).toBe("new");
  });

  it("builds geo, variants and trims values", () => {
    const payload = toPayload(
      form({ url: "  https://example.com  ", geo: [{ country: "de", url: " https://de.example.com " }, { country: "", url: "" }], abEnabled: true, variants: [{ url: "https://a.com", percentage: 70 }, { url: "https://b.com", percentage: 30 }] }),
      "create",
    );
    expect(payload.url).toBe("https://example.com");
    expect(payload.geo).toEqual({ DE: "https://de.example.com" });
    expect(payload.testVariants).toEqual([{ url: "https://a.com", percentage: 70 }, { url: "https://b.com", percentage: 30 }]);
  });
});

describe("date helpers", () => {
  it("round-trips local datetimes", () => {
    const local = "2026-07-04T09:30";
    expect(isoToLocal(localToIso(local))).toBe(local);
    expect(localToIso("")).toBeNull();
    expect(isoToLocal(null)).toBe("");
  });
});

describe("fromLink / activeSections", () => {
  const link = {
    url: "https://example.com/p", domain: "go.acme.com", key: "promo", tags: [{ id: "t1", name: "A", color: "red" }], folderId: "f1", comments: null,
    utm_source: "nl", utm_medium: null, utm_campaign: null, utm_term: null, utm_content: null, ref: null, expiresAt: null, expiredUrl: null,
    ios: null, android: null, geo: { US: "https://us.example.com" }, proxy: true, title: "Hello", description: null, image: null,
    rewrite: false, trackConversion: true, doIndex: false, testVariants: null,
  } as unknown as Link;

  it("maps an API link into form values", () => {
    const values = fromLink(link);
    expect(values).toMatchObject({ domain: "go.acme.com", key: "promo", tagIds: ["t1"], folderId: "f1", utm_source: "nl", password: "", abEnabled: false });
    expect(values.geo).toEqual([{ country: "US", url: "https://us.example.com" }]);
  });

  it("flags which advanced sections hold data", () => {
    expect(activeSections(fromLink(link))).toEqual({ utm: true, access: false, targeting: true, preview: true, abtest: false });
    expect(activeSections(emptyLinkForm)).toEqual({ utm: false, access: false, targeting: false, preview: false, abtest: false });
  });

  it("round-trips through toPayload without losing data", () => {
    const payload = toPayload(fromLink(link), "edit", false);
    expect(payload).toMatchObject({ url: "https://example.com/p", folderId: "f1", utm_source: "nl", geo: { US: "https://us.example.com" }, title: "Hello", proxy: true, trackConversion: true });
  });
});
