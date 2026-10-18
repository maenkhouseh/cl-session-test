"use client";

import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { formatCompact, formatCurrency, formatNumber } from "@/lib/utils/format";
import type { AnalyticsEvent, SeriesPoint } from "@/types/api";

const METRIC: Record<AnalyticsEvent, { key: "clicks" | "leads" | "saleAmount"; label: string; color: string }> = {
  clicks: { key: "clicks", label: "Clicks", color: "var(--chart-1)" },
  leads: { key: "leads", label: "Leads", color: "var(--chart-2)" },
  sales: { key: "saleAmount", label: "Revenue", color: "var(--chart-3)" },
};

/** Pick an x-axis label format that suits the bucket size (hour / day / month). */
function labelFormatter(points: SeriesPoint[]): (start: string) => string {
  if (points.length < 2) return (s) => s.slice(0, 10);
  const gap = new Date(points[1].start).getTime() - new Date(points[0].start).getTime();
  if (gap < 86_400_000) return (s) => new Intl.DateTimeFormat("en", { hour: "numeric", month: "short", day: "numeric" }).format(new Date(s));
  if (gap > 27 * 86_400_000) return (s) => new Intl.DateTimeFormat("en", { month: "short", year: "2-digit" }).format(new Date(s));
  return (s) => new Intl.DateTimeFormat("en", { month: "short", day: "numeric" }).format(new Date(s));
}

export function TimeseriesChart({ data, event }: { data: SeriesPoint[]; event: AnalyticsEvent }) {
  const metric = METRIC[event];
  const format = labelFormatter(data);
  const asValue = (v: number) => (event === "sales" ? formatCurrency(v) : formatNumber(v));

  return (
    <div className="h-72 w-full" role="img" aria-label={`${metric.label} over time`}>
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={data} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
          <defs>
            <linearGradient id={`fill-${event}`} x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={metric.color} stopOpacity={0.35} />
              <stop offset="100%" stopColor={metric.color} stopOpacity={0.02} />
            </linearGradient>
          </defs>
          <CartesianGrid vertical={false} stroke="var(--border)" />
          <XAxis dataKey="start" tickFormatter={format} tickLine={false} axisLine={false} minTickGap={32} tick={{ fill: "var(--muted-foreground)", fontSize: 12 }} />
          <YAxis tickFormatter={(v: number) => (event === "sales" ? formatCurrency(v).replace(/\.00$/, "") : formatCompact(v))} tickLine={false} axisLine={false} width={48} tick={{ fill: "var(--muted-foreground)", fontSize: 12 }} />
          <Tooltip
            cursor={{ stroke: "var(--border)" }}
            contentStyle={{ background: "var(--card)", border: "1px solid var(--border)", borderRadius: 8, fontSize: 13 }}
            labelFormatter={(l) => format(String(l))}
            formatter={(v) => [asValue(Number(v)), metric.label]}
          />
          <Area type="monotone" dataKey={metric.key} stroke={metric.color} strokeWidth={2} fill={`url(#fill-${event})`} isAnimationActive={false} />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}
