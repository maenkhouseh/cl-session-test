"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils/cn";

const ITEMS = [
  { href: "general", label: "General" },
  { href: "members", label: "Members" },
  { href: "domains", label: "Domains" },
  { href: "tags", label: "Tags" },
  { href: "folders", label: "Folders" },
  { href: "utm", label: "UTM templates" },
  { href: "api-keys", label: "API keys" },
  { href: "webhooks", label: "Webhooks" },
  { href: "billing", label: "Billing" },
];

export function SettingsNav({ slug }: { slug: string }) {
  const pathname = usePathname();
  return (
    <nav aria-label="Settings" className="flex gap-1 overflow-x-auto lg:w-48 lg:shrink-0 lg:flex-col">
      {ITEMS.map(({ href, label }) => {
        const active = pathname === `/app/${slug}/settings/${href}`;
        return (
          <Link key={href} href={`/app/${slug}/settings/${href}`} aria-current={active ? "page" : undefined} className={cn("whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors", active ? "bg-muted text-foreground" : "text-muted-foreground hover:bg-muted hover:text-foreground")}>
            {label}
          </Link>
        );
      })}
    </nav>
  );
}
