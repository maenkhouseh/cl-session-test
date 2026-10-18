import Link from "next/link";
import { formatCompact } from "@/lib/utils/format";
import type { Workspace } from "@/types/api";

function Meter({ label, used, limit }: { label: string; used: number; limit: number | null }) {
  const pct = limit ? Math.min(100, (used / limit) * 100) : 0;
  return (
    <div className="space-y-1">
      <div className="flex justify-between text-xs">
        <span className="text-muted-foreground">{label}</span>
        <span className="font-medium tabular-nums">{formatCompact(used)} / {limit === null ? "∞" : formatCompact(limit)}</span>
      </div>
      <div className="h-1.5 overflow-hidden rounded-full bg-muted" role="progressbar" aria-label={label} aria-valuenow={Math.round(pct)} aria-valuemin={0} aria-valuemax={100}>
        <div className={pct >= 90 ? "h-full bg-destructive" : "h-full bg-accent"} style={{ width: `${pct}%` }} />
      </div>
    </div>
  );
}

export function UsageMeter({ workspace }: { workspace: Workspace }) {
  return (
    <div className="space-y-3 rounded-lg border border-border bg-card p-3">
      <div className="flex items-center justify-between">
        <span className="text-xs font-semibold capitalize">{workspace.plan} plan</span>
        <Link href={`/app/${workspace.slug}/settings/billing`} className="text-xs text-accent hover:underline">Manage</Link>
      </div>
      <Meter label="New links" used={workspace.usage.links} limit={workspace.limits.links_per_month} />
      <Meter label="Tracked events" used={workspace.usage.events} limit={workspace.limits.events_per_month} />
    </div>
  );
}
