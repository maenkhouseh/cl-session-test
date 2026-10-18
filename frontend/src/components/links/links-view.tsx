"use client";

import { Link2, Plus } from "lucide-react";
import * as React from "react";
import { ConfirmDialog } from "@/components/forms/confirm-dialog";
import { PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { useDebounced } from "@/lib/hooks/use-debounced";
import { useBulkLinks, useDeleteLink, useLinks, useUpdateLink } from "@/lib/hooks/use-links";
import { useDomains, useFolders, useTags, useWorkspaceInfo } from "@/lib/hooks/use-library";
import { useWorkspaceApi } from "@/lib/hooks/use-workspace-api";
import type { Link, LinkFilters } from "@/types/api";
import { BulkActionBar } from "./bulk-action-bar";
import { LinkFiltersBar, domainOptions } from "./link-filters";
import { LinkFormDialog } from "./link-form-dialog";
import { LinkQrDialog } from "./link-qr-dialog";
import { LinkRow } from "./link-row";
import { LinkShareDialog } from "./link-share-dialog";

const PAGE_SIZE = 25;

export function LinksView() {
  const { slug } = useWorkspaceApi();
  const [filters, setFilters] = React.useState<LinkFilters>({ sortBy: "createdAt", sortOrder: "desc", page: 1, pageSize: PAGE_SIZE });
  const [search, setSearch] = React.useState("");
  const debouncedSearch = useDebounced(search);
  const query = React.useMemo(() => ({ ...filters, search: debouncedSearch || undefined }), [filters, debouncedSearch]);

  const links = useLinks(query);
  const tags = useTags();
  const folders = useFolders();
  const domains = useDomains();
  const workspace = useWorkspaceInfo();

  const [selected, setSelected] = React.useState<Set<string>>(new Set());
  const [editing, setEditing] = React.useState<{ link?: Link; template?: Link } | null>(null);
  const [qrLink, setQrLink] = React.useState<Link | null>(null);
  const [shareLink, setShareLink] = React.useState<Link | null>(null);
  const [deleting, setDeleting] = React.useState<Link[] | null>(null);

  const updateLink = useUpdateLink();
  const deleteLink = useDeleteLink();
  const bulk = useBulkLinks();

  const change = React.useCallback((patch: Partial<LinkFilters>) => {
    setFilters((f) => ({ ...f, ...patch, page: 1 }));
    setSelected(new Set());
  }, []);

  // "C" opens the create dialog from anywhere on the page (unless typing in a field).
  React.useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement;
      if (e.key.toLowerCase() !== "c" || e.metaKey || e.ctrlKey || e.altKey || target.closest("input, textarea, select, [contenteditable], [role=dialog]")) return;
      e.preventDefault();
      setEditing({});
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  const rows = links.data?.data ?? [];
  const folderById = React.useMemo(() => new Map((folders.data ?? []).map((f) => [f.id, f])), [folders.data]);
  const hasFilters = !!(debouncedSearch || filters.domain || filters.folderId || filters.tagIds?.length || filters.showArchived);
  const allSelected = rows.length > 0 && rows.every((l) => selected.has(l.id));

  const toggle = (id: string, on: boolean) => setSelected((s) => { const n = new Set(s); if (on) n.add(id); else n.delete(id); return n; });
  const selectedIds = [...selected];

  return (
    <div className="space-y-5">
      <PageHeader
        title="Links"
        description="Create, organise and track your short links."
        actions={<Button onClick={() => setEditing({})}><Plus /> Create link <kbd className="ml-1 hidden rounded bg-primary-foreground/20 px-1.5 text-xs sm:inline">C</kbd></Button>}
      />

      <LinkFiltersBar
        filters={filters}
        onChange={change}
        search={search}
        onSearch={(v) => { setSearch(v); setFilters((f) => ({ ...f, page: 1 })); }}
        tags={tags.data ?? []}
        folders={folders.data ?? []}
        domains={domainOptions(workspace.data?.systemDomains, domains.data)}
      />

      {links.isPending ? (
        <ul className="space-y-2" aria-busy="true" aria-label="Loading links">{Array.from({ length: 6 }, (_, i) => <li key={i}><Skeleton className="h-[74px] w-full rounded-xl" /></li>)}</ul>
      ) : links.isError ? (
        <EmptyState title="Couldn't load links" description="Check your connection and try again." action={<Button variant="secondary" onClick={() => links.refetch()}>Retry</Button>} />
      ) : rows.length === 0 ? (
        <EmptyState
          icon={<Link2 />}
          title={hasFilters ? "No links match your filters" : "No links yet"}
          description={hasFilters ? "Try a different search or clear your filters." : "Create your first short link to start tracking clicks."}
          action={hasFilters ? <Button variant="secondary" onClick={() => { setSearch(""); change({ domain: undefined, folderId: undefined, tagIds: undefined, showArchived: false }); }}>Clear filters</Button> : <Button onClick={() => setEditing({})}><Plus /> Create link</Button>}
        />
      ) : (
        <>
          <label className="flex items-center gap-2 px-1 text-sm text-muted-foreground">
            <input type="checkbox" className="size-4 accent-[var(--accent)]" checked={allSelected} onChange={(e) => setSelected(e.target.checked ? new Set(rows.map((l) => l.id)) : new Set())} />
            Select all on this page
          </label>
          <ul className="space-y-2" aria-label="Links">
            {rows.map((link) => (
              <LinkRow
                key={link.id}
                link={link}
                workspace={slug}
                folder={link.folderId ? folderById.get(link.folderId) : undefined}
                selected={selected.has(link.id)}
                onSelect={toggle}
                onEdit={(l) => setEditing({ link: l })}
                onDuplicate={(l) => setEditing({ template: l })}
                onQr={setQrLink}
                onShare={setShareLink}
                onArchive={(l) => updateLink.mutate({ id: l.id, archived: !l.archived })}
                onDelete={(l) => setDeleting([l])}
              />
            ))}
          </ul>
          <div className="flex items-center justify-between">
            <p className="text-sm text-muted-foreground">Page {links.data?.meta.page}</p>
            <div className="flex gap-2">
              <Button variant="secondary" size="sm" disabled={(filters.page ?? 1) <= 1} onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}>Previous</Button>
              <Button variant="secondary" size="sm" disabled={!links.data?.meta.hasMore} onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}>Next</Button>
            </div>
          </div>
        </>
      )}

      <BulkActionBar
        count={selected.size}
        folders={folders.data ?? []}
        showingArchived={!!filters.showArchived}
        busy={bulk.archive.isPending || bulk.move.isPending || bulk.remove.isPending}
        onClear={() => setSelected(new Set())}
        onArchive={() => bulk.archive.mutate({ ids: selectedIds, archived: !filters.showArchived }, { onSuccess: () => setSelected(new Set()) })}
        onMove={(folderId) => bulk.move.mutate({ ids: selectedIds, folderId }, { onSuccess: () => setSelected(new Set()) })}
        onDelete={() => setDeleting(rows.filter((l) => selected.has(l.id)))}
      />

      <LinkFormDialog
        open={!!editing}
        onOpenChange={(o) => !o && setEditing(null)}
        link={editing?.link}
        template={editing?.template}
      />
      <LinkQrDialog link={qrLink} open={!!qrLink} onOpenChange={(o) => !o && setQrLink(null)} />
      <LinkShareDialog link={shareLink} open={!!shareLink} onOpenChange={(o) => !o && setShareLink(null)} />
      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(o) => !o && setDeleting(null)}
        title={deleting && deleting.length > 1 ? `Delete ${deleting.length} links?` : "Delete link?"}
        description="The short links will stop working immediately. Their analytics history is kept."
        confirmLabel="Delete"
        destructive
        loading={deleteLink.isPending || bulk.remove.isPending}
        onConfirm={() => {
          if (!deleting) return;
          const done = { onSuccess: () => { setDeleting(null); setSelected(new Set()); } };
          if (deleting.length === 1) deleteLink.mutate(deleting[0].id, done);
          else bulk.remove.mutate(deleting.map((l) => l.id), done);
        }}
      />
    </div>
  );
}
