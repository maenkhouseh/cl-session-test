"use client";

import { Download, MousePointerClick, UserPlus, DollarSign } from "lucide-react";
import Link from "next/link";
import { Suspense } from "react";
import { FilterPills } from "@/components/analytics/filter-pills";
import { IntervalSelect } from "@/components/analytics/interval-select";
import { PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useAnalyticsParams } from "@/lib/hooks/use-analytics-params";
import { useEvents } from "@/lib/hooks/use-analytics";
import { useWorkspaceApi } from "@/lib/hooks/use-workspace-api";
import { countryFlag, countryName, formatCurrency, formatDateTime } from "@/lib/utils/format";
import type { AnalyticsEvent, EventRow } from "@/types/api";

const ICONS = { click: MousePointerClick, lead: UserPlus, sale: DollarSign } as const;
const VARIANT = { click: "accent", lead: "success", sale: "warning" } as const;

export function EventsView() {
  return (
    <Suspense>
      <EventsContent />
    </Suspense>
  );
}

function EventsContent() {
  const { slug, api } = useWorkspaceApi();
  const { interval, event, filters, params, update, clearFilters } = useAnalyticsParams("7d");
  const events = useEvents({ ...params, event });
  const rows = events.data?.pages.flatMap((p) => p.data) ?? [];

  return (
    <div className="space-y-5">
      <PageHeader
        title="Events"
        description="Every click, lead and sale as it happens."
        actions={
          <>
            <IntervalSelect value={interval} onChange={(v) => update({ interval: v })} />
            <Button asChild variant="secondary"><a href={api.analytics.exportUrl({ ...params, event })} download><Download /> Export</a></Button>
          </>
        }
      />

      <Tabs value={event} onValueChange={(e) => update({ event: e === "clicks" ? undefined : (e as AnalyticsEvent) })}>
        <TabsList>
          <TabsTrigger value="clicks">Clicks</TabsTrigger>
          <TabsTrigger value="leads">Leads</TabsTrigger>
          <TabsTrigger value="sales">Sales</TabsTrigger>
        </TabsList>
      </Tabs>

      <FilterPills filters={filters} onRemove={(k) => update({ [k]: undefined })} onClear={clearFilters} />

      <Card className="overflow-x-auto">
        {events.isPending ? (
          <div className="space-y-2 p-4" aria-busy="true">{Array.from({ length: 8 }, (_, i) => <Skeleton key={i} className="h-9 w-full" />)}</div>
        ) : events.isError ? (
          <EmptyState title="Couldn't load events" action={<Button variant="secondary" onClick={() => events.refetch()}>Retry</Button>} />
        ) : rows.length === 0 ? (
          <EmptyState icon={<MousePointerClick />} title="No events in this period" description="Try widening the date range or clearing filters." />
        ) : (
          <table className="w-full min-w-[44rem] text-sm">
            <thead className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
              <tr>
                <th scope="col" className="px-4 py-2.5 font-medium">Event</th>
                <th scope="col" className="px-4 py-2.5 font-medium">Link</th>
                <th scope="col" className="px-4 py-2.5 font-medium">{event === "clicks" ? "Referrer" : "Customer"}</th>
                <th scope="col" className="px-4 py-2.5 font-medium">Location</th>
                <th scope="col" className="px-4 py-2.5 font-medium">Device</th>
                {event === "sales" && <th scope="col" className="px-4 py-2.5 text-right font-medium">Amount</th>}
                <th scope="col" className="px-4 py-2.5 text-right font-medium">Time</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {rows.map((row, i) => <EventRowView key={`${row.clickId}-${row.timestamp}-${i}`} row={row} workspace={slug} showAmount={event === "sales"} />)}
            </tbody>
          </table>
        )}
      </Card>

      {events.hasNextPage && (
        <div className="flex justify-center">
          <Button variant="secondary" loading={events.isFetchingNextPage} onClick={() => events.fetchNextPage()}>Load more</Button>
        </div>
      )}
    </div>
  );
}

function EventRowView({ row, workspace, showAmount }: { row: EventRow; workspace: string; showAmount: boolean }) {
  const Icon = ICONS[row.event];
  return (
    <tr className="hover:bg-muted/40">
      <td className="px-4 py-2.5">
        <Badge variant={VARIANT[row.event]}><Icon className="size-3" /> {row.eventName ?? (row.event === "click" ? "Click" : row.event)}{row.qr ? " · QR" : ""}</Badge>
      </td>
      <td className="max-w-[14rem] truncate px-4 py-2.5">
        {row.link ? <Link href={`/app/${workspace}/analytics?linkId=${row.link.id}`} className="hover:underline">{row.link.domain}/{row.link.key}</Link> : <span className="text-muted-foreground">Deleted link</span>}
      </td>
      <td className="max-w-[12rem] truncate px-4 py-2.5 text-muted-foreground">
        {row.event === "click" ? (row.referer === "(direct)" ? "Direct" : row.referer ?? "—") : row.customer ? (
          <Link href={`/app/${workspace}/customers/${row.customer.id}`} className="text-foreground hover:underline">{row.customer.name ?? row.customer.email ?? row.customer.externalId}</Link>
        ) : "—"}
      </td>
      <td className="whitespace-nowrap px-4 py-2.5">{row.country ? <><span aria-hidden>{countryFlag(row.country)}</span> {row.city ? `${row.city}, ` : ""}{countryName(row.country)}</> : <span className="text-muted-foreground">Unknown</span>}</td>
      <td className="whitespace-nowrap px-4 py-2.5 text-muted-foreground">{[row.device, row.browser].filter(Boolean).join(" · ") || "—"}</td>
      {showAmount && <td className="px-4 py-2.5 text-right font-medium tabular-nums">{formatCurrency(row.amount ?? 0, row.currency)}</td>}
      <td className="whitespace-nowrap px-4 py-2.5 text-right text-muted-foreground">{formatDateTime(row.timestamp)}</td>
    </tr>
  );
}
