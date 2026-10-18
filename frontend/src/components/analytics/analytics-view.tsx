"use client";

import { useQuery } from "@tanstack/react-query";
import { Download } from "lucide-react";
import * as React from "react";
import { PageHeader } from "@/components/layout/page-header";
import { TimeseriesChart } from "@/components/charts/timeseries-chart";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { useAnalyticsParams } from "@/lib/hooks/use-analytics-params";
import { useTimeseries, useTotals } from "@/lib/hooks/use-analytics";
import { useFolders } from "@/lib/hooks/use-library";
import { useWorkspaceApi } from "@/lib/hooks/use-workspace-api";
import { FilterPills } from "./filter-pills";
import { IntervalSelect } from "./interval-select";
import { KpiTabs } from "./kpi-tabs";
import { TopList, type TopListTab } from "./top-list";

const LOCATIONS: TopListTab[] = [
  { groupBy: "country", label: "Countries", filterKey: "country" },
  { groupBy: "city", label: "Cities", filterKey: "city" },
  { groupBy: "region", label: "Regions", filterKey: "region" },
  { groupBy: "continent", label: "Continents", filterKey: "continent" },
];
const DEVICES: TopListTab[] = [
  { groupBy: "device", label: "Devices", filterKey: "device" },
  { groupBy: "browser", label: "Browsers", filterKey: "browser" },
  { groupBy: "os", label: "OS", filterKey: "os" },
  { groupBy: "trigger", label: "Trigger", filterKey: "trigger" },
];
const REFERRERS: TopListTab[] = [
  { groupBy: "referer", label: "Referrers", filterKey: "referer" },
  { groupBy: "refererUrl", label: "Referrer URLs", filterKey: "refererUrl" },
];
const LINKS: TopListTab[] = [
  { groupBy: "top_links", label: "Short links", filterKey: "linkId" },
  { groupBy: "top_urls", label: "Destination URLs", filterKey: "url" },
];
const UTMS: TopListTab[] = [
  { groupBy: "utm_source", label: "Source", filterKey: "utm_source" },
  { groupBy: "utm_medium", label: "Medium", filterKey: "utm_medium" },
  { groupBy: "utm_campaign", label: "Campaign", filterKey: "utm_campaign" },
  { groupBy: "utm_term", label: "Term", filterKey: "utm_term" },
  { groupBy: "utm_content", label: "Content", filterKey: "utm_content" },
];

export function AnalyticsView() {
  const { slug, api } = useWorkspaceApi();
  const { interval, event, filters, params, update, clearFilters } = useAnalyticsParams();
  const totals = useTotals(params);
  const series = useTimeseries({ ...params, event });
  const folders = useFolders();

  const link = useQuery({ queryKey: ["ws", slug, "links", filters.linkId], queryFn: () => api.links.get(filters.linkId), enabled: !!filters.linkId });
  const names = React.useMemo(
    () => ({ ...Object.fromEntries((folders.data ?? []).map((f) => [f.id, f.name])), ...(link.data ? { [link.data.id]: `${link.data.domain}/${link.data.key}` } : {}) }),
    [folders.data, link.data],
  );
  const setFilter = React.useCallback((key: string, value: string) => update({ [key]: value }), [update]);

  return (
    <div className="space-y-5">
      <PageHeader
        title="Analytics"
        description="Clicks, leads and revenue across your links."
        actions={
          <>
            <IntervalSelect value={interval} onChange={(v) => update({ interval: v })} />
            <Button asChild variant="secondary"><a href={api.analytics.exportUrl({ ...params, event })} download><Download /> Export</a></Button>
          </>
        }
      />

      <FilterPills filters={filters} names={names} onRemove={(k) => update({ [k]: undefined })} onClear={clearFilters} />

      <KpiTabs totals={totals.data} active={event} onSelect={(e) => update({ event: e === "clicks" ? undefined : e })} />

      <Card>
        <CardContent>
          {series.isPending ? <Skeleton className="h-72 w-full" /> : series.isError ? <p className="py-24 text-center text-sm text-muted-foreground">Couldn&apos;t load the chart.</p> : <TimeseriesChart data={series.data} event={event} />}
        </CardContent>
      </Card>

      <div className="grid gap-4 lg:grid-cols-2">
        <TopList title="Links" tabs={LINKS} event={event} params={params} onFilter={setFilter} />
        <TopList title="Locations" tabs={LOCATIONS} event={event} params={params} onFilter={setFilter} />
        <TopList title="Devices" tabs={DEVICES} event={event} params={params} onFilter={setFilter} />
        <TopList title="Referrers" tabs={REFERRERS} event={event} params={params} onFilter={setFilter} />
        <TopList title="UTM parameters" tabs={UTMS} event={event} params={params} onFilter={setFilter} />
      </div>
    </div>
  );
}
