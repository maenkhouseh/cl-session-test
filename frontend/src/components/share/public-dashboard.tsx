"use client";

import { useQuery } from "@tanstack/react-query";
import { Lock } from "lucide-react";
import * as React from "react";
import { IntervalSelect } from "@/components/analytics/interval-select";
import { KpiTabs } from "@/components/analytics/kpi-tabs";
import { TopList, type TopListTab } from "@/components/analytics/top-list";
import { TimeseriesChart } from "@/components/charts/timeseries-chart";
import { Logo } from "@/components/layout/logo";
import { ThemeToggle } from "@/components/layout/theme-toggle";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { publicApi } from "@/lib/api";
import { PublicAnalyticsSource } from "@/lib/hooks/analytics-source";
import { useTimeseries, useTotals } from "@/lib/hooks/use-analytics";
import type { AnalyticsEvent, Interval } from "@/types/api";

const TABS: { title: string; tabs: TopListTab[] }[] = [
  { title: "Locations", tabs: [{ groupBy: "country", label: "Countries" }, { groupBy: "city", label: "Cities" }] },
  { title: "Devices", tabs: [{ groupBy: "device", label: "Devices" }, { groupBy: "browser", label: "Browsers" }, { groupBy: "os", label: "OS" }] },
  { title: "Referrers", tabs: [{ groupBy: "referer", label: "Referrers" }] },
];

/** Read-only analytics for one shared link (no login). Prompts for a password when the owner set one. */
export function PublicDashboard({ token }: { token: string }) {
  const [password, setPassword] = React.useState<string | undefined>();
  const [attempt, setAttempt] = React.useState("");
  const info = useQuery({ queryKey: ["public", token, "info", password], queryFn: () => publicApi.dashboard(token, password), retry: false });

  if (info.isPending) return <Shell><Skeleton className="h-96 w-full" /></Shell>;
  if (info.isError || !info.data) return <Shell><EmptyState title="Dashboard not found" description="This link may have been unshared or never existed." /></Shell>;

  if (info.data.locked) {
    return (
      <Shell>
        <Card className="mx-auto max-w-sm">
          <CardContent>
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); setPassword(attempt); }}>
              <div className="flex items-center gap-2 font-medium"><Lock className="size-4" /> This dashboard is password protected</div>
              <Input type="password" aria-label="Password" autoFocus value={attempt} onChange={(e) => setAttempt(e.target.value)} />
              {password !== undefined && <p role="alert" className="text-sm text-destructive">Incorrect password.</p>}
              <Button type="submit" className="w-full">View analytics</Button>
            </form>
          </CardContent>
        </Card>
      </Shell>
    );
  }

  const link = info.data.link!;
  return (
    <Shell title={`${link.domain}/${link.key}`} subtitle={link.url}>
      <PublicAnalyticsSource token={token} password={password}>
        <Body />
      </PublicAnalyticsSource>
    </Shell>
  );
}

function Body() {
  const [interval, setInterval] = React.useState<Interval>("30d");
  const [event, setEvent] = React.useState<AnalyticsEvent>("clicks");
  const params = React.useMemo(() => ({ interval }), [interval]);
  const totals = useTotals(params);
  const series = useTimeseries({ ...params, event });

  return (
    <div className="space-y-5">
      <div className="flex justify-end"><IntervalSelect value={interval} onChange={setInterval} /></div>
      <KpiTabs totals={totals.data} active={event} onSelect={setEvent} />
      <Card><CardContent>{series.isPending ? <Skeleton className="h-72 w-full" /> : <TimeseriesChart data={series.data ?? []} event={event} />}</CardContent></Card>
      <div className="grid gap-4 lg:grid-cols-3">
        {TABS.map((t) => <TopList key={t.title} title={t.title} tabs={t.tabs} event={event} params={params} onFilter={() => undefined} />)}
      </div>
    </div>
  );
}

function Shell({ title, subtitle, children }: { title?: string; subtitle?: string; children: React.ReactNode }) {
  return (
    <div className="mx-auto min-h-screen max-w-5xl space-y-6 px-4 py-8">
      <header className="flex items-center justify-between"><Logo /><ThemeToggle /></header>
      {title && <div><h1 className="text-2xl font-semibold tracking-tight">{title}</h1><p className="truncate text-sm text-muted-foreground">{subtitle}</p></div>}
      {children}
      <footer className="pt-6 text-center text-xs text-muted-foreground">Powered by Shortie</footer>
    </div>
  );
}
