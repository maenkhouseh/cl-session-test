"use client";

import { Check } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { PLAN_CARDS } from "@/lib/constants/plans";
import { useWorkspaceInfo } from "@/lib/hooks/use-library";
import { cn } from "@/lib/utils/cn";
import { formatDate, formatNumber } from "@/lib/utils/format";
import { SettingsCard } from "./settings-card";

function UsageBar({ label, used, limit }: { label: string; used: number; limit: number | null }) {
  const pct = limit ? Math.min(100, (used / limit) * 100) : 0;
  return (
    <div className="space-y-1.5">
      <div className="flex justify-between text-sm"><span>{label}</span><span className="tabular-nums text-muted-foreground">{formatNumber(used)} / {limit === null ? "Unlimited" : formatNumber(limit)}</span></div>
      <div className="h-2 overflow-hidden rounded-full bg-muted" role="progressbar" aria-label={label} aria-valuenow={Math.round(pct)} aria-valuemin={0} aria-valuemax={100}><div className={cn("h-full", pct >= 90 ? "bg-destructive" : "bg-accent")} style={{ width: `${pct}%` }} /></div>
    </div>
  );
}

export function BillingSettings() {
  const info = useWorkspaceInfo();
  if (info.isPending || !info.data) return <Skeleton className="h-96 w-full" />;
  const w = info.data;

  return (
    <>
      <SettingsCard title="Current plan" description={w.usageResetAt ? `Usage resets on ${formatDate(w.usageResetAt)}` : undefined} actions={<Badge variant="accent" className="capitalize">{w.plan}</Badge>}>
        <div className="max-w-lg space-y-4">
          <UsageBar label="New links this month" used={w.usage.links} limit={w.limits.links_per_month} />
          <UsageBar label="Tracked events this month" used={w.usage.events} limit={w.limits.events_per_month} />
          <p className="text-sm text-muted-foreground">Analytics retention: {w.limits.retention_days ? `${Math.round(w.limits.retention_days / 365 * 10) / 10 >= 1 ? `${Math.round(w.limits.retention_days / 365)} year(s)` : `${w.limits.retention_days} days`}` : "unlimited"} · API rate limit: {formatNumber(w.limits.rate_limit)}/min</p>
        </div>
      </SettingsCard>

      <div className="grid gap-4 md:grid-cols-2">
        {PLAN_CARDS.map((p) => {
          const current = p.plan === w.plan;
          return (
            <Card key={p.plan} className={cn("flex flex-col p-5", current && "border-accent")}>
              <div className="flex items-baseline justify-between"><h3 className="font-semibold">{p.name}</h3><p className="text-2xl font-semibold">{p.price}<span className="text-sm font-normal text-muted-foreground">{p.price.startsWith("$") && p.price !== "$0" ? "/mo" : ""}</span></p></div>
              <p className="mt-1 text-sm text-muted-foreground">{p.blurb}</p>
              <ul className="my-4 flex-1 space-y-1.5 text-sm">{p.highlights.map((h) => <li key={h} className="flex gap-2"><Check className="mt-0.5 size-4 shrink-0 text-success" />{h}</li>)}</ul>
              {current ? <Button variant="secondary" disabled>Current plan</Button> : <Button variant="secondary" asChild><a href="mailto:sales@shortie.example?subject=Plan%20change">Contact us to switch</a></Button>}
            </Card>
          );
        })}
      </div>
    </>
  );
}
