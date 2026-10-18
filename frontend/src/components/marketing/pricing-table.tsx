import { Check, Minus } from "lucide-react";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { PLAN_CARDS } from "@/lib/constants/plans";
import { cn } from "@/lib/utils/cn";

const ROWS: { label: string; values: [string, string, string, string] }[] = [
  { label: "New links / month", values: ["25", "10K", "50K", "Unlimited"] },
  { label: "Tracked events / month", values: ["1K", "250K", "1M", "Unlimited"] },
  { label: "Analytics retention", values: ["30 days", "3 years", "5 years", "Unlimited"] },
  { label: "Custom domains", values: ["3", "100", "250", "Unlimited"] },
  { label: "Folders", values: ["—", "20", "50", "Unlimited"] },
  { label: "Team members", values: ["1", "10", "20", "Unlimited"] },
  { label: "API rate limit", values: ["60 / min", "1,200 / min", "3,000 / min", "Custom"] },
  { label: "Conversion tracking", values: ["—", "✓", "✓", "✓"] },
  { label: "A/B tests, geo & device targeting", values: ["—", "✓", "✓", "✓"] },
  { label: "Webhooks", values: ["—", "✓", "✓", "✓"] },
  { label: "Password & expiring links", values: ["—", "✓", "✓", "✓"] },
  { label: "SAML/SSO & audit logs", values: ["—", "—", "—", "✓"] },
];

export function PricingCards() {
  return (
    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      {PLAN_CARDS.map((p) => {
        const featured = p.plan === "advanced";
        return (
          <div key={p.plan} className={cn("flex flex-col rounded-2xl border bg-card p-6", featured ? "border-accent shadow-lg shadow-accent/10" : "border-border")}>
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-semibold">{p.name}</h3>
              {featured && <span className="rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground">Best value</span>}
            </div>
            <p className="mt-4 text-4xl font-semibold tracking-tight">{p.price}{p.price.startsWith("$") && p.price !== "$0" && <span className="text-base font-normal text-muted-foreground">/mo</span>}</p>
            <p className="mt-2 text-sm text-muted-foreground">{p.blurb}</p>
            <ul className="my-6 flex-1 space-y-2 text-sm">
              {p.highlights.map((h) => <li key={h} className="flex gap-2"><Check className="mt-0.5 size-4 shrink-0 text-success" />{h}</li>)}
            </ul>
            <Button asChild variant={featured ? "accent" : "secondary"}><Link href={p.plan === "enterprise" ? "/register" : "/register"}>{p.plan === "enterprise" ? "Get a demo" : p.plan === "free" ? "Start for free" : "Start 14-day trial"}</Link></Button>
          </div>
        );
      })}
    </div>
  );
}

export function ComparisonTable() {
  return (
    <div className="overflow-x-auto rounded-xl border border-border">
      <table className="w-full min-w-[40rem] text-sm">
        <caption className="sr-only">Plan comparison</caption>
        <thead className="bg-muted/60 text-left">
          <tr>
            <th scope="col" className="px-4 py-3 font-medium">Feature</th>
            {PLAN_CARDS.map((p) => <th key={p.plan} scope="col" className="px-4 py-3 font-semibold">{p.name}</th>)}
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {ROWS.map((row) => (
            <tr key={row.label}>
              <th scope="row" className="px-4 py-3 text-left font-normal text-muted-foreground">{row.label}</th>
              {row.values.map((v, i) => <td key={i} className="px-4 py-3">{v === "—" ? <Minus className="size-4 text-muted-foreground" aria-label="Not included" /> : v === "✓" ? <Check className="size-4 text-success" aria-label="Included" /> : v}</td>)}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
