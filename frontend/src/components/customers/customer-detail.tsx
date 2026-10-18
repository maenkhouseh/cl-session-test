"use client";

import { ArrowLeft, DollarSign, UserPlus } from "lucide-react";
import Link from "next/link";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { useCustomer } from "@/lib/hooks/use-team";
import { useWorkspaceApi } from "@/lib/hooks/use-workspace-api";
import { countryFlag, formatCurrency, formatDate, formatDateTime } from "@/lib/utils/format";

export function CustomerDetail({ id }: { id: string }) {
  const { slug } = useWorkspaceApi();
  const query = useCustomer(id);

  if (query.isPending) return <div className="space-y-4" aria-busy="true"><Skeleton className="h-10 w-64" /><Skeleton className="h-64 w-full" /></div>;
  if (query.isError) return <EmptyState title="Customer not found" action={<Link className="text-accent hover:underline" href={`/app/${slug}/customers`}>Back to customers</Link>} />;

  const { data: customer, lifetimeValue, activity } = query.data;

  return (
    <div className="space-y-5">
      <Link href={`/app/${slug}/customers`} className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"><ArrowLeft className="size-4" /> Customers</Link>
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{customer.name ?? customer.externalId}</h1>
        {customer.email && <p className="text-sm text-muted-foreground">{customer.email}</p>}
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <Stat label="Lifetime value" value={formatCurrency(lifetimeValue)} />
        <Stat label="Customer since" value={formatDate(customer.createdAt)} />
        <Stat label="Country" value={customer.country ? `${countryFlag(customer.country)} ${customer.country}` : "—"} />
      </div>

      <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
        <Card>
          <CardHeader><CardTitle>Activity</CardTitle></CardHeader>
          <CardContent>
            {activity.length === 0 ? <p className="text-sm text-muted-foreground">No activity yet.</p> : (
              <ol className="space-y-4">
                {activity.map((a, i) => (
                  <li key={i} className="flex gap-3">
                    <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-muted [&_svg]:size-3.5">{a.type === "sale" ? <DollarSign /> : <UserPlus />}</span>
                    <div className="min-w-0">
                      <p className="text-sm font-medium">{a.type === "sale" ? `${a.eventName} · ${formatCurrency(a.amount ?? 0, a.currency)}` : a.eventName}</p>
                      <p className="text-xs text-muted-foreground">{formatDateTime(a.timestamp)}{a.invoiceId ? ` · ${a.invoiceId}` : ""}</p>
                    </div>
                  </li>
                ))}
              </ol>
            )}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle>Attribution</CardTitle></CardHeader>
          <CardContent className="space-y-1 text-sm">
            {customer.link ? (
              <>
                <p className="text-muted-foreground">First touch</p>
                <Link href={`/app/${slug}/analytics?linkId=${customer.link.id}`} className="font-medium hover:underline">{customer.link.domain}/{customer.link.key}</Link>
              </>
            ) : <p className="text-muted-foreground">Not attributed to a link.</p>}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <Card className="p-5">
      <p className="text-sm text-muted-foreground">{label}</p>
      <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
    </Card>
  );
}
