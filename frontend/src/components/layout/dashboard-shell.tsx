"use client";

import { Activity, BarChart3, Link2, LogOut, Menu, Settings, Users, X } from "lucide-react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import * as React from "react";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { useWorkspaceInfo } from "@/lib/hooks/use-library";
import { useLogout, useMe, useWorkspaces } from "@/lib/hooks/use-session";
import { cn } from "@/lib/utils/cn";
import { Logo } from "./logo";
import { ThemeToggle } from "./theme-toggle";
import { UsageMeter } from "./usage-meter";
import { WorkspaceSwitcher } from "./workspace-switcher";

const NAV = [
  { href: "links", label: "Links", icon: Link2 },
  { href: "analytics", label: "Analytics", icon: BarChart3 },
  { href: "events", label: "Events", icon: Activity },
  { href: "customers", label: "Customers", icon: Users },
  { href: "settings/general", label: "Settings", icon: Settings, match: "settings" },
] as const;

export function DashboardShell({ slug, children }: { slug: string; children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  // Keyed by pathname so the mobile menu closes itself on navigation without an effect.
  const [openAt, setOpenAt] = React.useState<string | null>(null);
  const open = openAt === pathname;
  const me = useMe();
  const workspaces = useWorkspaces();
  const info = useWorkspaceInfo();
  const logout = useLogout(() => router.replace("/login"));

  const sidebar = (
    <div className="flex h-full flex-col gap-4 p-3">
      <Logo href={`/app/${slug}/links`} className="px-1 pt-1" />
      <WorkspaceSwitcher current={slug} workspaces={workspaces.data ?? []} />
      <nav className="flex flex-col gap-0.5" aria-label="Main">
        {NAV.map(({ href, label, icon: Icon, ...rest }) => {
          const target = `/app/${slug}/${href}`;
          const key = "match" in rest ? rest.match : href;
          const active = pathname.startsWith(`/app/${slug}/${key}`);
          return (
            <Link key={href} href={target} aria-current={active ? "page" : undefined} className={cn("flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors", active ? "bg-muted text-foreground" : "text-muted-foreground hover:bg-muted hover:text-foreground")}>
              <Icon className="size-4" /> {label}
            </Link>
          );
        })}
      </nav>
      <div className="mt-auto space-y-3">
        {info.data && <UsageMeter workspace={info.data} />}
        <div className="flex items-center justify-between">
          <DropdownMenu>
            <DropdownMenuTrigger className="flex min-w-0 items-center gap-2 rounded-lg p-1.5 hover:bg-muted">
              <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">{me.data?.name.charAt(0).toUpperCase()}</span>
              <span className="truncate text-sm">{me.data?.name}</span>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" side="top" className="w-56">
              <DropdownMenuLabel>{me.data?.email}</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem onSelect={() => logout.mutate()}>
                <LogOut /> Log out
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
          <ThemeToggle />
        </div>
      </div>
    </div>
  );

  return (
    <div className="min-h-screen lg:grid lg:grid-cols-[16rem_1fr]">
      <aside className="sticky top-0 hidden h-screen border-r border-border bg-background lg:block">{sidebar}</aside>

      <div className="flex items-center justify-between border-b border-border px-4 py-2.5 lg:hidden">
        <Logo href={`/app/${slug}/links`} />
        <Button variant="ghost" size="icon" aria-label={open ? "Close menu" : "Open menu"} aria-expanded={open} onClick={() => setOpenAt(open ? null : pathname)}>
          {open ? <X /> : <Menu />}
        </Button>
      </div>
      {open && <div className="fixed inset-0 top-[53px] z-40 overflow-y-auto bg-background lg:hidden">{sidebar}</div>}

      <main className="min-w-0 px-4 py-6 sm:px-8 sm:py-8">
        <div className="mx-auto max-w-6xl">{children}</div>
      </main>
    </div>
  );
}
