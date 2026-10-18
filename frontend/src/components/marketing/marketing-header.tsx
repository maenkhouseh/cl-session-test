import Link from "next/link";
import { Logo } from "@/components/layout/logo";
import { ThemeToggle } from "@/components/layout/theme-toggle";
import { Button } from "@/components/ui/button";

const NAV = [
  { href: "/links", label: "Links" },
  { href: "/analytics", label: "Analytics" },
  { href: "/pricing", label: "Pricing" },
];

export function MarketingHeader() {
  return (
    <header className="sticky top-0 z-40 border-b border-border bg-background/80 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4 sm:px-6">
        <Logo />
        <nav aria-label="Primary" className="hidden items-center gap-1 md:flex">
          {NAV.map((item) => (
            <Link key={item.href} href={item.href} className="rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">{item.label}</Link>
          ))}
        </nav>
        <div className="ml-auto flex items-center gap-2">
          <ThemeToggle />
          <Button asChild variant="ghost" className="hidden sm:inline-flex"><Link href="/login">Log in</Link></Button>
          <Button asChild><Link href="/register">Sign up</Link></Button>
        </div>
      </div>
      <nav aria-label="Primary mobile" className="flex gap-1 overflow-x-auto border-t border-border px-4 py-1 md:hidden">
        {NAV.map((item) => <Link key={item.href} href={item.href} className="rounded-lg px-3 py-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">{item.label}</Link>)}
      </nav>
    </header>
  );
}
