"use client";

import { Check, ChevronsUpDown, Plus } from "lucide-react";
import Link from "next/link";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import type { Workspace } from "@/types/api";

export function WorkspaceSwitcher({ current, workspaces }: { current: string; workspaces: Workspace[] }) {
  const active = workspaces.find((w) => w.slug === current);

  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="flex w-full items-center gap-2 rounded-lg border border-border bg-card px-2.5 py-2 text-left text-sm hover:bg-muted">
        <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-accent text-xs font-semibold text-accent-foreground">
          {(active?.name ?? "?").charAt(0).toUpperCase()}
        </span>
        <span className="min-w-0 flex-1 truncate font-medium">{active?.name ?? current}</span>
        <ChevronsUpDown className="size-4 shrink-0 text-muted-foreground" />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-60">
        <DropdownMenuLabel>Workspaces</DropdownMenuLabel>
        {workspaces.map((w) => (
          <DropdownMenuItem key={w.id} asChild>
            <Link href={`/app/${w.slug}/links`}>
              <span className="flex size-5 items-center justify-center rounded bg-muted text-[10px] font-semibold">{w.name.charAt(0).toUpperCase()}</span>
              <span className="flex-1 truncate">{w.name}</span>
              {w.slug === current && <Check />}
            </Link>
          </DropdownMenuItem>
        ))}
        <DropdownMenuSeparator />
        <DropdownMenuItem asChild>
          <Link href="/app/new">
            <Plus /> Create workspace
          </Link>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
