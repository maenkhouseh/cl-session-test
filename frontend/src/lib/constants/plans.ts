import type { Plan } from "@/types/api";

/** Mirrors backend config/shortie.php so the UI can gate options before the API rejects them. */
export type Feature =
  | "ab_testing" | "password" | "geo_targeting" | "device_targeting" | "cloaking" | "expiration"
  | "deep_links" | "custom_preview" | "webhooks" | "conversion_tracking";

const PAID: Feature[] = ["ab_testing", "password", "geo_targeting", "device_targeting", "cloaking", "expiration", "deep_links", "custom_preview", "webhooks", "conversion_tracking"];

const FEATURES: Record<Plan, Feature[]> = { free: [], business: PAID, advanced: PAID, enterprise: PAID };

export function planAllows(plan: Plan | undefined, feature: Feature): boolean {
  return !!plan && FEATURES[plan].includes(feature);
}

export const PLAN_ORDER: Plan[] = ["free", "business", "advanced", "enterprise"];

export interface PlanCard {
  plan: Plan;
  name: string;
  price: string;
  blurb: string;
  highlights: string[];
}

export const PLAN_CARDS: PlanCard[] = [
  { plan: "free", name: "Free", price: "$0", blurb: "Kick the tires with the essentials.", highlights: ["25 new links / month", "1K tracked events / month", "30-day analytics retention", "3 custom domains", "QR codes, tags, UTM builder"] },
  { plan: "business", name: "Business", price: "$30", blurb: "For teams that live in their link analytics.", highlights: ["10K new links / month", "250K tracked events / month", "3-year retention", "100 custom domains, 20 folders", "Conversion tracking & webhooks", "A/B tests, geo & device targeting", "1,200 req/min API"] },
  { plan: "advanced", name: "Advanced", price: "$90", blurb: "High-volume attribution at scale.", highlights: ["50K new links / month", "1M tracked events / month", "5-year retention", "250 domains, 50 folders", "20 users", "3,000 req/min API", "Priority support"] },
  { plan: "enterprise", name: "Enterprise", price: "Custom", blurb: "Unlimited scale with white-glove support.", highlights: ["Unlimited links & events", "Unlimited retention", "SAML/SSO & audit logs", "Custom rate limits", "Dedicated Slack support"] },
];
