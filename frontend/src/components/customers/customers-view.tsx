"use client";

import { Search, Users } from "lucide-react";
import Link from "next/link";
import * as React from "react";
import { PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { useDebounced } from "@/lib/hooks/use-debounced";
import { useCustomers } from "@/lib/hooks/use-team";
import { useWorkspaceApi } from "@/lib/hooks/use-workspace-api";
import { countryFlag, formatDate } from "@/lib/utils/format";

export function CustomersView() {
  const { slug } = useWorkspaceApi();
  const [search, setSearch] = React.useState("");
  const [page, setPage] = React.useState(1);
  const term = useDebounced(search);
  const customers = useCustomers({ search: term || undefined, page, pageSize: 25 });
  const rows = customers.data?.data ?? [];

  return (
    <div className="space-y-5">
      <PageHeader title="Customers" description="People who converted from your links, with their full journey." />

      <div className="relative max-w-md">
        <Search className="pointer-events-none absolute left-3 top-2.5 size-4 text-muted-foreground" />
        <Input type="search" aria-label="Search customers" placeholder="Search by name, email or ID…" className="pl-9" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
      </div>

      <Card className="overflow-x-auto">
        {customers.isPending ? (
          <div className="space-y-2 p-4" aria-busy="true">{Array.from({ length: 6 }, (_, i) => <Skeleton key={i} className="h-10 w-full" />)}</div>
        ) : rows.length === 0 ? (
          <EmptyState icon={<Users />} title={term ? "No customers match your search" : "No customers yet"} description={term ? undefined : "Customers appear once you track a lead from a link with conversion tracking enabled."} />
        ) : (
          <table className="w-full min-w-[40rem] text-sm">
            <thead className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
              <tr><th scope="col" className="px-4 py-2.5 font-medium">Customer</th><th scope="col" className="px-4 py-2.5 font-medium">Attributed link</th><th scope="col" className="px-4 py-2.5 font-medium">Country</th><th scope="col" className="px-4 py-2.5 text-right font-medium">Since</th></tr>
            </thead>
            <tbody className="divide-y divide-border">
              {rows.map((c) => (
                <tr key={c.id} className="hover:bg-muted/40">
                  <td className="px-4 py-2.5"><Link href={`/app/${slug}/customers/${c.id}`} className="font-medium hover:underline">{c.name ?? c.externalId}</Link>{c.email && <p className="text-xs text-muted-foreground">{c.email}</p>}</td>
                  <td className="px-4 py-2.5">{c.link ? `${c.link.domain}/${c.link.key}` : <span className="text-muted-foreground">—</span>}</td>
                  <td className="px-4 py-2.5">{c.country ? <><span aria-hidden>{countryFlag(c.country)}</span> {c.country}</> : "—"}</td>
                  <td className="px-4 py-2.5 text-right text-muted-foreground">{formatDate(c.createdAt)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>

      {(page > 1 || customers.data?.meta.hasMore) && (
        <div className="flex justify-end gap-2">
          <Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Previous</Button>
          <Button variant="secondary" size="sm" disabled={!customers.data?.meta.hasMore} onClick={() => setPage((p) => p + 1)}>Next</Button>
        </div>
      )}
    </div>
  );
}
