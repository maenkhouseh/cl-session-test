"use client";

import { ChevronDown, Lock } from "lucide-react";
import Link from "next/link";
import * as React from "react";
import { Badge } from "@/components/ui/badge";
import { useWorkspaceApi } from "@/lib/hooks/use-workspace-api";
import { cn } from "@/lib/utils/cn";

interface SectionProps {
  title: string;
  description: string;
  icon: React.ReactNode;
  defaultOpen?: boolean;
  /** When set, the section is shown but disabled with an upgrade prompt. */
  locked?: boolean;
  /** Small indicator that the section currently holds data. */
  active?: boolean;
  children: React.ReactNode;
}

/** Collapsible advanced-options group inside the link dialog. */
export function Section({ title, description, icon, defaultOpen, locked, active, children }: SectionProps) {
  const [open, setOpen] = React.useState(!!defaultOpen && !locked);
  const { slug } = useWorkspaceApi();
  const id = React.useId();

  return (
    <div className="rounded-lg border border-border">
      <button type="button" aria-expanded={open} aria-controls={id} onClick={() => setOpen((o) => !o)} className="flex w-full items-center gap-3 px-3.5 py-3 text-left">
        <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground [&_svg]:size-4">{icon}</span>
        <span className="min-w-0 flex-1">
          <span className="flex items-center gap-2 text-sm font-medium">
            {title}
            {active && <span className="size-1.5 rounded-full bg-accent" aria-label="Configured" />}
            {locked && <Badge variant="warning"><Lock className="size-3" /> Upgrade</Badge>}
          </span>
          <span className="block truncate text-xs text-muted-foreground">{description}</span>
        </span>
        <ChevronDown className={cn("size-4 shrink-0 text-muted-foreground transition-transform", open && "rotate-180")} />
      </button>
      {open && (
        <div id={id} className="space-y-4 border-t border-border px-3.5 py-4">
          {locked ? (
            <p className="text-sm text-muted-foreground">
              This option isn&apos;t included in your current plan.{" "}
              <Link href={`/app/${slug}/settings/billing`} className="font-medium text-accent hover:underline">See plans</Link>
            </p>
          ) : children}
        </div>
      )}
    </div>
  );
}
