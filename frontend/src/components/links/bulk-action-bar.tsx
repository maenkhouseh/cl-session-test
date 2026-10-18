"use client";

import { Archive, FolderInput, Trash2, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import type { Folder } from "@/types/api";

interface Props {
  count: number;
  folders: Folder[];
  showingArchived: boolean;
  busy: boolean;
  onClear: () => void;
  onArchive: () => void;
  onMove: (folderId: string | null) => void;
  onDelete: () => void;
}

/** Floating toolbar shown while one or more links are selected. */
export function BulkActionBar({ count, folders, showingArchived, busy, onClear, onArchive, onMove, onDelete }: Props) {
  if (count === 0) return null;

  return (
    <div role="toolbar" aria-label="Bulk actions" className="fixed inset-x-4 bottom-4 z-30 mx-auto flex max-w-xl items-center gap-2 rounded-xl border border-border bg-card p-2 pl-4 shadow-xl">
      <span className="flex-1 text-sm font-medium">{count} selected</span>
      <DropdownMenu>
        <DropdownMenuTrigger asChild><Button variant="secondary" size="sm" disabled={busy}><FolderInput /> Move</Button></DropdownMenuTrigger>
        <DropdownMenuContent align="end" side="top">
          <DropdownMenuItem onSelect={() => onMove(null)}>No folder</DropdownMenuItem>
          {folders.filter((f) => f.accessLevel === "write").map((f) => <DropdownMenuItem key={f.id} onSelect={() => onMove(f.id)}>{f.name}</DropdownMenuItem>)}
        </DropdownMenuContent>
      </DropdownMenu>
      <Button variant="secondary" size="sm" disabled={busy} onClick={onArchive}><Archive /> {showingArchived ? "Unarchive" : "Archive"}</Button>
      <Button variant="destructive" size="sm" disabled={busy} onClick={onDelete}><Trash2 /> Delete</Button>
      <Button variant="ghost" size="icon" aria-label="Clear selection" onClick={onClear}><X /></Button>
    </div>
  );
}
