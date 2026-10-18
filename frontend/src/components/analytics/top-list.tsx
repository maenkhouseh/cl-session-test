"use client";

import * as React from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { Card } from "@/components/ui/card";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useTop } from "@/lib/hooks/use-analytics";
import { cn } from "@/lib/utils/cn";
import { continentName, countryFlag, countryName, formatCompact, formatCurrency } from "@/lib/utils/format";
import { displayUrl, faviconUrl } from "@/lib/utils/url";
import type { AnalyticsEvent, AnalyticsParams, GroupBy, TopLinkRow, TopRow } from "@/types/api";

export interface TopListTab {
  groupBy: GroupBy;
  label: string;
  /** Filter key set when a row is clicked (omit for non-filterable lists). */
  filterKey?: string;
}

interface Props {
  title: string;
  tabs: TopListTab[];
  event: AnalyticsEvent;
  params: AnalyticsParams;
  onFilter: (key: string, value: string) => void;
}

/** One ranked breakdown card with switchable dimensions; only the visible tab is fetched. */
export function TopList({ title, tabs, event, params, onFilter }: Props) {
  const [active, setActive] = React.useState(tabs[0].groupBy);
  const tab = tabs.find((t) => t.groupBy === active) ?? tabs[0];
  const query = useTop(tab.groupBy, { ...params, event });

  const rows = (query.data ?? []) as TopRow[];
  const metric = (r: TopRow) => (event === "clicks" ? r.clicks : event === "leads" ? r.leads : r.sales) ?? 0;
  const max = Math.max(1, ...rows.map(metric));

  return (
    <Card className="flex h-[22rem] flex-col">
      <div className="flex items-center justify-between gap-3 border-b border-border px-4 py-2.5">
        <h3 className="text-sm font-semibold">{title}</h3>
        <Tabs value={active} onValueChange={(v) => setActive(v as GroupBy)}>
          <TabsList className="p-0.5">
            {tabs.map((t) => <TabsTrigger key={t.groupBy} value={t.groupBy} className="px-2 py-1 text-xs">{t.label}</TabsTrigger>)}
          </TabsList>
        </Tabs>
      </div>
      <div className="flex-1 overflow-y-auto p-2">
        {query.isPending ? (
          <div className="space-y-1.5 p-2">{Array.from({ length: 6 }, (_, i) => <Skeleton key={i} className="h-7 w-full" />)}</div>
        ) : rows.length === 0 ? (
          <p className="flex h-full items-center justify-center text-sm text-muted-foreground">No data for this period</p>
        ) : (
          <ul>
            {rows.map((row, i) => {
              const label = rowLabel(tab.groupBy, row);
              const clickable = !!tab.filterKey && label.value !== undefined;
              const content = (
                <>
                  <span className="absolute inset-y-0 left-0 rounded-md bg-accent/10" style={{ width: `${(metric(row) / max) * 100}%` }} aria-hidden />
                  <span className="relative flex min-w-0 items-center gap-2">{label.node}</span>
                  <span className="relative text-sm font-medium tabular-nums">{event === "sales" ? formatCurrency(row.saleAmount ?? 0) : formatCompact(metric(row))}</span>
                </>
              );
              return (
                <li key={`${label.value ?? i}-${i}`}>
                  {clickable ? (
                    <button type="button" onClick={() => onFilter(tab.filterKey as string, label.value as string)} className={cn("relative flex w-full items-center justify-between gap-3 rounded-md px-2 py-1.5 text-left hover:bg-muted/60")}>{content}</button>
                  ) : (
                    <div className="relative flex w-full items-center justify-between gap-3 px-2 py-1.5">{content}</div>
                  )}
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </Card>
  );
}

/** Display node + the value used as a filter when the row is clicked. */
function rowLabel(groupBy: GroupBy, row: TopRow): { node: React.ReactNode; value?: string } {
  const text = (v: unknown) => (v === null || v === undefined || v === "" ? "Unknown" : String(v));

  switch (groupBy) {
    case "country": {
      const code = row.country as string | null;
      return { value: code ?? undefined, node: <><span aria-hidden>{countryFlag(code)}</span><span className="truncate text-sm">{countryName(code)}</span></> };
    }
    case "continent":
      return { value: (row.continent as string) || undefined, node: <span className="truncate text-sm">{continentName(row.continent as string)}</span> };
    case "top_links": {
      const link = (row as TopLinkRow).link;
      return { value: link.deleted ? undefined : link.id, node: <span className="truncate text-sm">{link.deleted ? "Deleted link" : `${link.domain}/${link.key}`}</span> };
    }
    case "top_urls": {
      const url = String(row.url ?? "");
      return { value: url || undefined, node: <>{url && <img src={faviconUrl(url, 32)} alt="" width={16} height={16} className="size-4 rounded-sm" />}<span className="truncate text-sm" title={url}>{displayUrl(url, 48)}</span></> };
    }
    case "referer": {
      const host = String(row.referer ?? "");
      return {
        value: host || undefined,
        node: <>{host && host !== "(direct)" && <img src={faviconUrl(`https://${host}`, 32)} alt="" width={16} height={16} className="size-4 rounded-sm" />}<span className="truncate text-sm">{host === "(direct)" ? "Direct" : text(host)}</span></>,
      };
    }
    case "trigger": {
      const value = String(row.trigger);
      return { value, node: <span className="text-sm">{value === "qr" ? "QR scan" : "Link click"}</span> };
    }
    default: {
      const key = groupBy === "refererUrl" ? "refererUrl" : groupBy;
      const raw = row[key];
      return { value: raw === null || raw === undefined || raw === "" ? undefined : String(raw), node: <span className="truncate text-sm">{text(raw)}</span> };
    }
  }
}
