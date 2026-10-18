"use client";

import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils/cn";
import { formatCompact, formatCurrency, formatNumber } from "@/lib/utils/format";
import type { AnalyticsEvent, Totals } from "@/types/api";

interface Props {
  totals?: Totals;
  active: AnalyticsEvent;
  onSelect: (event: AnalyticsEvent) => void;
}

const TABS: { event: AnalyticsEvent; label: string }[] = [
  { event: "clicks", label: "Clicks" },
  { event: "leads", label: "Leads" },
  { event: "sales", label: "Sales" },
];

/** Selectable headline numbers; the chosen one drives the chart and the breakdown lists below. */
export function KpiTabs({ totals, active, onSelect }: Props) {
  return (
    <div role="tablist" aria-label="Metric" className="grid grid-cols-1 divide-y divide-border overflow-hidden rounded-xl border border-border bg-card sm:grid-cols-3 sm:divide-x sm:divide-y-0">
      {TABS.map(({ event, label }) => {
        const value = totals ? (event === "clicks" ? totals.clicks : event === "leads" ? totals.leads : totals.sales) : null;
        const selected = event === active;
        return (
          <button
            key={event}
            role="tab"
            aria-selected={selected}
            onClick={() => onSelect(event)}
            className={cn("space-y-1 px-5 py-4 text-left transition-colors hover:bg-muted/60", selected && "bg-muted/60 shadow-[inset_0_-2px_0_var(--accent)]")}
          >
            <span className="text-sm text-muted-foreground">{label}</span>
            {value === null ? <Skeleton className="h-8 w-24" /> : <span className="block text-3xl font-semibold tabular-nums" title={formatNumber(value)}>{formatCompact(value)}</span>}
            <span className="block h-4 text-xs text-muted-foreground">
              {totals && event === "clicks" && `${formatCompact(totals.uniqueClicks)} unique`}
              {totals && event === "sales" && `${formatCurrency(totals.saleAmount)} revenue`}
            </span>
          </button>
        );
      })}
    </div>
  );
}
