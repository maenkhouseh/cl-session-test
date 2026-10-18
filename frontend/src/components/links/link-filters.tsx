"use client";

import { Search, Tag } from "lucide-react";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input, Select } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import type { Domain, Folder, LinkFilters, LinkSort, Tag as TagModel } from "@/types/api";

const SORTS: { value: string; label: string }[] = [
  { value: "createdAt:desc", label: "Newest" },
  { value: "createdAt:asc", label: "Oldest" },
  { value: "clicks:desc", label: "Most clicks" },
  { value: "saleAmount:desc", label: "Most revenue" },
  { value: "lastClicked:desc", label: "Recently clicked" },
];

interface Props {
  filters: LinkFilters;
  onChange: (patch: Partial<LinkFilters>) => void;
  search: string;
  onSearch: (value: string) => void;
  tags: TagModel[];
  folders: Folder[];
  domains: string[];
}

export function LinkFiltersBar({ filters, onChange, search, onSearch, tags, folders, domains }: Props) {
  const selectedTags = filters.tagIds ?? [];

  return (
    <div className="flex flex-col gap-2 lg:flex-row lg:items-center" role="search" aria-label="Filter links">
      <div className="relative flex-1">
        <Search className="pointer-events-none absolute left-3 top-2.5 size-4 text-muted-foreground" />
        <Input type="search" aria-label="Search links" placeholder="Search by short link, URL or title…" className="pl-9" value={search} onChange={(e) => onSearch(e.target.value)} />
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <Select aria-label="Domain" className="w-auto min-w-36" value={filters.domain ?? ""} onChange={(e) => onChange({ domain: e.target.value || undefined })}>
          <option value="">All domains</option>
          {domains.map((d) => <option key={d} value={d}>{d}</option>)}
        </Select>

        <Select aria-label="Folder" className="w-auto min-w-36" value={filters.folderId ?? ""} onChange={(e) => onChange({ folderId: e.target.value || undefined })}>
          <option value="">All folders</option>
          <option value="none">No folder</option>
          {folders.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
        </Select>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="secondary"><Tag /> Tags{selectedTags.length > 0 && <span className="rounded bg-accent px-1.5 text-xs text-accent-foreground">{selectedTags.length}</span>}</Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="max-h-72 w-56 overflow-y-auto">
            <DropdownMenuLabel>Filter by tag</DropdownMenuLabel>
            <DropdownMenuSeparator />
            {tags.length === 0 && <p className="px-2.5 py-2 text-sm text-muted-foreground">No tags yet</p>}
            {tags.map((tag) => (
              <DropdownMenuCheckboxItem
                key={tag.id}
                checked={selectedTags.includes(tag.id)}
                onSelect={(e) => e.preventDefault()}
                onCheckedChange={(checked) => onChange({ tagIds: checked ? [...selectedTags, tag.id] : selectedTags.filter((id) => id !== tag.id) })}
              >
                {tag.name}
              </DropdownMenuCheckboxItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>

        <Select
          aria-label="Sort by"
          className="w-auto min-w-36"
          value={`${filters.sortBy ?? "createdAt"}:${filters.sortOrder ?? "desc"}`}
          onChange={(e) => {
            const [sortBy, sortOrder] = e.target.value.split(":");
            onChange({ sortBy: sortBy as LinkSort, sortOrder: sortOrder as "asc" | "desc" });
          }}
        >
          {SORTS.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
        </Select>

        <label className="flex items-center gap-2 text-sm">
          <Switch checked={!!filters.showArchived} onCheckedChange={(showArchived) => onChange({ showArchived })} aria-label="Show archived" />
          Archived
        </label>
      </div>
    </div>
  );
}

export function domainOptions(systemDomains: string[] | undefined, domains: Domain[] | undefined): string[] {
  return [...(systemDomains ?? []), ...(domains ?? []).map((d) => d.slug)];
}
